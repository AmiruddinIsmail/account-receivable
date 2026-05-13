<?php

namespace App\Console\Commands;

use App\Enums\AccountAllocationActionEnum;
use App\Enums\AccountAllocationComponentEnum;
use App\Enums\AccountCommandTypeEnum;
use App\Models\AccountInvoice;
use App\Models\AccountMonthlySnapshot;
use App\Models\AccountPaymentAllocation;
use App\Models\AccountStatement;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlySnapshots extends Command
{
    protected $signature = 'account:generate-snapshots {--month= : The month to generate for (YYYY-MM)} {--all : Process all historic months with activity}';

    protected $description = 'Generate point-in-time monthly snapshots using allocation history';

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
        // Find the earliest event date to start from
        $firstEvent = AccountStatement::min('occured_at');
        
        if (!$firstEvent) {
            $this->error("No account activity found in Statement of Account.");
            return;
        }

        $start = Carbon::parse($firstEvent)->startOfMonth();
        $end = Carbon::parse('2024-01-01')->startOfMonth();

        while ($start < $end) {
            $this->generateForMonth($start->copy());
            $start->addMonth();
        }

        $this->info("All historic months processed.");
    }

    protected function generateForMonth(Carbon $targetMonth)
    {
        $yearMonth = $targetMonth->format('Y-m');
        $endOfMonth = $targetMonth->copy()->endOfMonth();
        $startOfMonth = $targetMonth->copy()->startOfMonth();

        $this->info("Generating point-in-time snapshots for {$yearMonth}...");

        // Get all impact accounts up to this point in time
        $accountIds = AccountStatement::where('occured_at', '<=', $endOfMonth->toDateString())
            ->distinct()
            ->pluck('account_id');

        foreach ($accountIds as $accountId) {
            // Immutability Check
            if (AccountMonthlySnapshot::where('account_id', $accountId)->where('year_month', $yearMonth)->exists()) {
                $this->warn("Account {$accountId} already has a record for {$yearMonth}. Skipping.");
                continue;
            }

            // 1. Get Opening Balance (Historical Closing of previous month)
            $previousMonthStr = $targetMonth->copy()->subMonth()->format('Y-m');
            $prevSnapshot = AccountMonthlySnapshot::where('account_id', $accountId)
                ->where('year_month', $previousMonthStr)
                ->first();
            $openingBalance = $prevSnapshot ? $prevSnapshot->closing_balance : 0;

            // 2. Metrics for this specific month from Statement (Raw activity)
            $monthlyMetrics = AccountStatement::where('account_id', $accountId)
                ->whereBetween('occured_at', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->selectRaw('
                    SUM(CASE WHEN type = "'.AccountCommandTypeEnum::INVOICE->value.'" THEN debit_amt ELSE 0 END) as billed_principal,
                    SUM(CASE WHEN type = "'.AccountCommandTypeEnum::LATE_CHARGE->value.'" THEN debit_amt ELSE 0 END) as billed_late_charge,
                    SUM(credit_amt) as total_collected
                ')->first();

            // 3. POINT-IN-TIME BALANCE RECONSTRUCTION
            // Logic: Total Debt Created <= EOM minus Total Allocations <= EOM
            
            // Total Debt Created up to EOM
            $totalDebt = AccountInvoice::where('account_id', $accountId)
                ->where('occured_at', '<=', $endOfMonth->toDateString())
                ->selectRaw('SUM(principal_billed_amt) as p_total, SUM(late_charge_billed_amt) as l_total')
                ->first();

            // Total Allocations applied up to EOM (from the historical allocation table)
            // We sum both 'allocate' (+) and 'reverse' (-) to get the net paid back then.
            $historicalPaid = AccountPaymentAllocation::where('account_id', $accountId)
                ->where('created_at', '<=', $endOfMonth->copy()->addDay()->startOfDay()) // Use creation date for allocation timing
                ->get();
            
            $pPaid = 0;
            $lPaid = 0;

            foreach ($historicalPaid as $alloc) {
                $impact = ($alloc->action === AccountAllocationActionEnum::REVERSE->value) ? -$alloc->amount : $alloc->amount;
                if ($alloc->component === AccountAllocationComponentEnum::COMPONENT_PRINCIPAL->value) $pPaid += $impact;
                if ($alloc->component === AccountAllocationComponentEnum::COMPONENT_LATE_CHARGE->value) $lPaid += $impact;
            }

            $principalBalance = max(0, ($totalDebt->p_total ?? 0) - $pPaid);
            $lateChargeBalance = max(0, ($totalDebt->l_total ?? 0) - $lPaid);
            $closingBalance = $principalBalance + $lateChargeBalance;

            // 4. MIA Score Calculation based on historical billed principal
            $avgBilled = AccountMonthlySnapshot::where('account_id', $accountId)
                ->where('year_month', '<', $yearMonth)
                ->latest('year_month')
                ->limit(3)
                ->avg('billed_principal') ?: ($monthlyMetrics->billed_principal ?? 1);

            $miaScore = $principalBalance / max(1, $avgBilled);

            AccountMonthlySnapshot::create([
                'account_id' => $accountId,
                'year_month' => $yearMonth,
                'opening_balance_amt' => $openingBalance,
                'closing_balance_amt' => $closingBalance,
                'principal_balance_amt' => $principalBalance,
                'late_charge_balance_amt' => $lateChargeBalance,
                'principal_billed_amt' => (int) ($monthlyMetrics->billed_principal ?? 0),
                'late_charge_billed_amt' => (int) ($monthlyMetrics->billed_late_charge ?? 0),
                'payment_received_amt' => (int) ($monthlyMetrics->total_collected ?? 0),
                'mia_score' => ceil($miaScore),
            ]);

            // $this->line("Processed: {$accountId}");
        }
    }
}
