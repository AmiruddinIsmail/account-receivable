<?php

namespace App\Externals\Spider\Repositories;

use Illuminate\Support\Facades\DB;

final class PaymentRepository
{
    public function getPayments(string $startDate, string $endDate)
    {
        $bindings = [
            $startDate, $endDate,
        ];

        return DB::connection('spider_mysql')->cursor($this->getQuery(), $bindings);
    }

    private function getQuery(): string
    {
        return "
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
}
