<?php

namespace App\Externals\Spider\Actions;

use App\Aggregates\AccountAggregate;
use App\Enums\InvoiceTypeEnum;
use App\Externals\Spider\Repositories\TransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\Facades\Projectionist;

class ProcessTransactionToEvent
{
    public function handle(string $startDate, string $endDate, ?callable $output, bool $muteProjectors, ?string $accountId, bool $local = false)
    {
        DB::disableQueryLog();

        if ($muteProjectors) {
            $output && $output('info', 'NOTICE: Muting all projectors for fast historical import. Run `php artisan event-sourcing:replay` afterwards.');
            Projectionist::withoutEventHandlers();
        }

        $repository = new TransactionRepository;
        if ($local === false) {
            $transactions = $repository->getTransactions($startDate, $endDate);
        } elseif ($accountId === null) {
            $transactions = $repository->getLocalTransactions($startDate, $endDate);
        } else {
            $transactions = $repository->getLocalTransactionsForAccount($startDate, $endDate, $accountId);
        }

        $batchCount = 0;
        $totalProcessed = 0;
        $aggregates = [];
        foreach ($transactions as $transaction) {
            try {
                $this->processTransactionRow($transaction, $aggregates);
                $batchCount++;
                $totalProcessed++;
                if ($batchCount >= 1000) {
                    $output && $output('info', "Processed {$totalProcessed} transactions. Persisting batch (Current Date: {$transaction->date_at})...");
                    $this->persistAggregates($aggregates);
                    $aggregates = [];
                    $batchCount = 0;

                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }

            } catch (\Exception $e) {
                $errorMsg = 'Error processing row '.json_encode($transaction).': '.$e->getMessage();
                Log::error("\n".$errorMsg);
                $output && $output('error', $errorMsg);
            }
        }

        if (! empty($aggregates)) {
            $output && $output('info', 'Persisting final batch of '.count($aggregates)." aggregates (Total processed: {$totalProcessed})...");
            $this->persistAggregates($aggregates);
        }
    }

    protected function processTransactionRow($row, array &$aggregates)
    {
        $mandate = $row->mandate ?? null;
        if (! $mandate) {
            return;
        }

        $type = $row->type ?? null;
        $date = $row->date_at ?? null;
        $referenceNo = $row->reference_no ?? null;
        $amountCents = (int) round(((float) $row->amount ?? 0) * 100);
        $tenure = $row->tenure ?? null;
        $subscriptionAmt = (int) round(((float) $row->subscription_amt) * 100);
        $securityDepositCount = $row->security_deposit_count ?? 0;

        if (! isset($aggregates[$mandate])) {
            $aggregates[$mandate] = AccountAggregate::retrieve($mandate);
        }

        $aggregate = $aggregates[$mandate];

        switch ($type) {
            case 'invoice':
                $invoiceData = $this->processInvoiceAmount($row, $aggregate);
                if ($invoiceData['amount'] > 0) {
                    $aggregate->invoiceCreated(
                        referenceNo: $referenceNo,
                        occurredAt: $date,
                        amount: $invoiceData['amount'],
                        type: $invoiceData['type'],
                        tenure: $tenure,
                        subscriptionAmt: $securityDepositCount > 0 ? $invoiceData['amount'] : $subscriptionAmt,
                    );
                }
                break;
            case 'payment':
                $aggregate->paymentReceived(
                    referenceNo: $referenceNo,
                    occurredAt: $date,
                    amount: $amountCents,
                    tenure: $tenure,
                );
                break;
            case 'lpc':
                $invoiceNo = str_replace('LATE-', '', $referenceNo);
                $aggregate->lateChargeApplied(
                    referenceNo: $referenceNo,
                    occurredAt: $date,
                    amount: $amountCents,
                    invoiceNo: $invoiceNo,
                );
                break;
            case 'cn':
                $aggregate->creditNoteIssued(
                    referenceNo: $referenceNo,
                    occurredAt: $date,
                    amount: $amountCents,
                    tenure: $tenure,
                );
                break;
            case 'refund':
                $aggregate->refundIssued(
                    referenceNo: $referenceNo,
                    occurredAt: $date,
                    amount: $amountCents,
                    tenure: $tenure,
                );
                break;
            default:
                break;
        }
    }

    protected function persistAggregates(array $aggregates)
    {
        foreach ($aggregates as $aggregate) {
            $aggregate->persist();
        }
    }

    protected function processInvoiceAmount($row, AccountAggregate $aggregate)
    {
        /** invoice monthly amount */
        $amount = (int) round(((float) $row->amount ?? 0) * 100);
        $subscriptionAmt = (int) round(((float) $row->subscription_amt ?? 0) * 100);
        $programFeeAmt = (int) round(((float) $row->program_fee ?? 0) * 100);

        if ($aggregate->getInvoicesCount() === 0) {

            /** invoice monthly equal as subscription amt + program fee */
            if ($amount === ($subscriptionAmt + $programFeeAmt)) {
                return [
                    'amount' => $amount,
                    'type' => InvoiceTypeEnum::INVOICE->value,
                ];
            }

            if ($row->security_deposit_count > 0) {
                return [
                    'amount' => $amount,
                    'type' => InvoiceTypeEnum::INVOICE->value,
                ];
            }

            return [
                'amount' => $amount,
                'type' => InvoiceTypeEnum::INVOICE->value,
            ];

        }

        /** there is some case where invoice issue less than RM 1 */
        if ($amount < 100) {
            return [
                'amount' => 0,
                'type' => 'Others',
            ];
        }

        if ($row->security_deposit_count > 0) {
            return [
                'amount' => $this->calculateSecurityDepositDeducted($amount, $subscriptionAmt, $row->security_deposit_count, $row->tenure),
                'type' => InvoiceTypeEnum::INVOICE->value,
            ];
        }

        /** if description got monthly charge then use subscription amount */
        if (str_contains($row->description, 'Monthly Charge')) {
            return [
                'amount' => $subscriptionAmt,
                'type' => InvoiceTypeEnum::INVOICE->value,
            ];
        }

        if (str_contains($row->description, 'Device Re-activation')) {
            return [
                'amount' => $amount,
                'type' => InvoiceTypeEnum::REACTIVATION_FEE->value,
            ];
        }

        if (str_contains($row->description, 'Device Condition')) {
            return [
                'amount' => $amount,
                'type' => InvoiceTypeEnum::UPGRADE_TERMINATE_FEE->value,
            ];
        }

        return [
            'amount' => $amount,
            'type' => InvoiceTypeEnum::INVOICE->value,
        ];
    }

    protected function calculateSecurityDepositDeducted(int $amount, int $subscriptionAmt, int $securityDepositCount, int $tenure)
    {
        if ($securityDepositCount <= 0) {
            return $amount;
        }

        return $amount - (int) round(($subscriptionAmt * $securityDepositCount) / ($tenure - 1));
    }
}
