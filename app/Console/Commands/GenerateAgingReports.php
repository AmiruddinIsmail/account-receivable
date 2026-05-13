<?php

namespace App\Console\Commands;

use App\Enums\AccountAllocationActionEnum;
use App\Models\AccountInvoice;
use App\Models\AccountAgingReport;
use App\Models\AccountPaymentAllocation;
use App\Models\AccountStatement;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateAgingReports extends Command
{
    protected $signature = 'account:generate-aging {--month= : The month to generate for (YYYY-MM)} {--all : Process all historic months}';

    protected $description = 'Generate a traditional bucketed aging report (Current, 30, 60, 90, 120+)';

    public function handle()
    {
        if ($this->option('all')) {
            $this->processAllMonths();
            return;
        }

        $targetMonth = $this->option('month') 
            ? Carbon::createFromFormat('Y-m', $this->option('month')) 
            : Carbon::now()->subMonth();
            
        $this->generateForMonth($targetMonth);
    }

    protected function processAllMonths()
    {
        $firstEvent = AccountStatement::min('occured_at');
        if (!$firstEvent) return;

        $start = Carbon::parse($firstEvent)->startOfMonth();
        $end = Carbon::parse('2024-01-01')->startOfMonth();

        while ($start < $end) {
            $this->generateForMonth($start->copy());
            $start->addMonth();
        }
    }

    protected function generateForMonth(Carbon $targetMonth)
    {
        $yearMonth = $targetMonth->format('Y-m');
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        $this->info("Generating Aging Report for {$yearMonth}...");

        $accountIds = AccountStatement::where('occured_at', '<=', $endOfMonth->toDateString())
            ->distinct()
            ->pluck('account_id');

        foreach ($accountIds as $accountId) {
            
            if (AccountAgingReport::where('account_id', $accountId)->where('year_month', $yearMonth)->exists()) {
                continue;
            }

            // 1. Get ALL invoices created on or before EOM
            $invoices = AccountInvoice::where('account_id', $accountId)
                ->where('occured_at', '<=', $endOfMonth->toDateString())
                ->get();

            // 2. Get ALL allocations applied on or before EOM
            // Group them by invoice number for easier math
            $allocations = AccountPaymentAllocation::where('account_id', $accountId)
                ->where('created_at', '<=', $endOfMonth->copy()->addDay()->startOfDay())
                ->get()
                ->groupBy('invoice_no');
                       
            $buckets = [
                'current' => 0,
                '30' => 0,
                '60' => 0,
                '90' => 0,
                '120' => 0
            ];

            foreach ($invoices as $invoice) {
                // Total debt for this invoice back then
                $invoiceTotalDebt = $invoice->principal_billed_amt + $invoice->late_charge_billed_amt;

                // How much was paid toward this invoice back then?
                $invoiceAllocations = $allocations->get($invoice->reference_no, collect());
                $paidBackThen = 0;
                foreach ($invoiceAllocations as $alloc) {
                    $paidBackThen += ($alloc->action === AccountAllocationActionEnum::REVERSE->value ? -$alloc->amount : $alloc->amount);
                }

                $outstanding = max(0, $invoiceTotalDebt - $paidBackThen);                

                if ($outstanding > 0) {
                    // Place into bucket based on invoice age relative to endOfMonth
                    $invoiceDate = Carbon::parse($invoice->occured_at);
                    $diffInMonths = $invoiceDate->diffInMonths($endOfMonth);

                    if ($diffInMonths == 0) $buckets['current'] += $outstanding;
                    elseif ($diffInMonths <= 1) $buckets['30'] += $outstanding;
                    elseif ($diffInMonths <= 2) $buckets['60'] += $outstanding;
                    elseif ($diffInMonths <= 3) $buckets['90'] += $outstanding;
                    else $buckets['120'] += $outstanding;
                }
            }
                                
            AccountAgingReport::create([
                'account_id' => $accountId,
                'year_month' => $yearMonth,
                'bucket_current' => $buckets['current'],
                'bucket_30_days' => $buckets['30'],
                'bucket_60_days' => $buckets['60'],
                'bucket_90_days' => $buckets['90'],
                'bucket_120_plus' => $buckets['120'],
                'total_outstanding' => array_sum($buckets),
            ]);

            // $this->line("Aged: {$accountId}");
        }
    }
}
