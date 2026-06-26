<?php

namespace App\Externals\Spider\Repositories;

use Illuminate\Support\Facades\DB;

class TransactionRepository
{
    public function getTransactions(string $startDate, string $endDate)
    {
        $bindings = [
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
        ];

        return DB::connection('spider_mysql')->cursor($this->getQuery(), $bindings);
    }

    private function getQuery(): string
    {
        return "
            SELECT 
                DATE(ai.issue_date) AS date_at,
                'invoice' AS type,
                o.orderId AS mandate,
                CONCAT('INV-', ai.id) AS running_id,
                ai.invoice_no AS reference_no,
                ai.customer_id,
                ai.total_amount AS amount,
                o.tenure,
                o.monthly_subscription AS subscription_amt,
                1 AS sort_order,
                COALESCE(o.program_fee, 0) AS program_fee,
                ai.total_amount - COALESCE(o.program_fee, 0) AS amt_diff,
                ai.description,
                o.is_second_order AS multi_device,
                COALESCE(o.security_deposit_count, 0) AS security_deposit_count,
                (case when cc.contract_status_id = 1 then 'active' when cc.contract_status_id in (5,6,7) then 'completed' else 'active' end) as contract_status
            FROM
                ar_invoices ai
                INNER JOIN `order` o ON o.customer_id = ai.customer_id AND o.order_status_id IN (1, 2, 3)
                LEFT JOIN customer_contracts cc ON cc.customer_id = ai.customer_id
            WHERE
                ai.issue_date BETWEEN ? AND ?
            UNION ALL
            SELECT
                DATE(ai.issue_date) AS date_at,
                'lpc' AS type,
                o.orderId AS mandate,
                CONCAT('LATE-', ai.id) AS running_id,
                CONCAT('LATE-', ai.invoice_no) AS reference_no,
                ai.customer_id,
                ai.late_payment_charges AS amount,
                o.tenure,
                o.monthly_subscription AS subscription_amt,
                2 AS sort_order,
                0 AS program_fee,
                0 AS amt_diff,
                'Late charge' AS description,
                o.is_second_order AS multi_device,
                COALESCE(o.security_deposit_count, 0) AS security_deposit_count,
                (case when cc.contract_status_id = 1 then 'active' when cc.contract_status_id in (5,6,7) then 'completed' else 'active' end) as contract_status
            FROM
                ar_invoices ai
                INNER JOIN `order` o ON o.customer_id = ai.customer_id AND o.order_status_id IN (1, 2, 3)
                LEFT JOIN customer_contracts cc ON cc.customer_id = ai.customer_id
            WHERE
                ai.issue_date BETWEEN ? AND ?
                AND ai.late_payment_charges > 0
            UNION ALL
            SELECT
                DATE(cn.issue_date) AS date_at,
                'cn' AS type,
                o.orderId AS mandate,
                CONCAT('CN-', cn.id) AS running_id,
                cn.credit_notes_no AS reference_no,
                cn.customer_id,
                cn.total_amount AS amount,
                o.tenure,
                o.monthly_subscription AS subscription_amt,
                3 AS sort_order,
                0 AS program_fee,
                0 AS amt_diff,
                cn.description,
                o.is_second_order AS multi_device,
                COALESCE(o.security_deposit_count, 0) AS security_deposit_count,
                (case when cc.contract_status_id = 1 then 'active' when cc.contract_status_id in (5,6,7) then 'completed' else 'active' end) as contract_status
            FROM
                ar_credit_notes cn
                INNER JOIN `order` o ON o.customer_id = cn.customer_id AND o.order_status_id IN (1, 2, 3)
                LEFT JOIN customer_contracts cc ON cc.customer_id = cn.customer_id
            WHERE
                cn.issue_date BETWEEN ? AND ?
            UNION ALL
            SELECT
                DATE(ABS.transaction_at) AS date_at,
                LOWER(ABS.type) AS type,
                o.orderId AS mandate,
                CONCAT('PAY-', ABS.id) AS running_id,
                ABS.checksum AS reference_no,
                ABS.customer_id,
                ABS.amount,
                o.tenure,
                o.monthly_subscription AS subscription_amt,
                4 AS sort_order,
                0 AS program_fee,
                0 AS amt_diff,
                ABS.description,
                o.is_second_order AS multi_device,
                COALESCE(o.security_deposit_count, 0) AS security_deposit_count,
                (case when cc.contract_status_id = 1 then 'active' when cc.contract_status_id in (5,6,7) then 'completed' else 'active' end) as contract_status
            FROM
                account_bank_statements ABS
                INNER JOIN `order` o ON o.customer_id = ABS.customer_id AND o.order_status_id IN (1, 2, 3)
                LEFT JOIN customer_contracts cc ON cc.customer_id = ABS.customer_id
            WHERE
                ABS.transaction_at BETWEEN ? AND ?
                AND ABS.customer_id IS NOT NULL
                AND EXISTS (
                    SELECT 1
                    FROM ar_invoices ai
                    WHERE ai.customer_id = ABS.customer_id
                )
            ORDER BY
                date_at ASC,
                sort_order ASC;
        ";
    }

    public function getLocalTransactions(string $startDate, string $endDate)
    {
        $bindings = [
            $startDate, $endDate,
        ];

        return DB::cursor($this->getLocalQuery(), $bindings);
    }

    private function getLocalQuery()
    {
        return '
            SELECT * FROM spider_transactions
            WHERE date_at BETWEEN ? AND ?
            ORDER BY
                customer_id ASC,
                date_at ASC,
                sort_order ASC;
        ';
    }

    public function getLocalTransactionsForAccount(string $startDate, string $endDate, ?string $accountId)
    {
        $bindings = [
            $startDate, $endDate, $accountId,
        ];

        return DB::cursor($this->getLocalQueryForAccount(), $bindings);
    }

    private function getLocalQueryForAccount()
    {
        return '
            SELECT * FROM spider_transactions
            WHERE date_at BETWEEN ? AND ?
            AND mandate = ?
            ORDER BY
                customer_id ASC,
                date_at ASC,
                sort_order ASC;
        ';
    }
}
