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
                CONCAT('INV-', ai.id) AS id,
                ai.invoice_no AS reference_no,
                ai.customer_id,
                ai.total_amount AS amount,
                1 AS sort_order
            FROM
                ar_invoices ai
                LEFT JOIN `order` o ON o.customer_id = ai.customer_id
            WHERE
                ai.issue_date BETWEEN ? AND ?
            UNION ALL
            SELECT
                DATE(ai.issue_date) AS date_at,
                'lpc' AS type,
                o.orderId AS mandate,
                CONCAT('LATE-', ai.id) AS id,
                CONCAT('LATE-', ai.invoice_no) AS reference_no,
                ai.customer_id,
                ai.late_payment_charges AS amount,
                2 AS sort_order
            FROM
                ar_invoices ai
                LEFT JOIN `order` o ON o.customer_id = ai.customer_id
            WHERE
                ai.issue_date BETWEEN ? AND ?
                AND ai.late_payment_charges > 0
            UNION ALL
            SELECT
                DATE(cn.issue_date) AS date_at,
                'cn' AS type,
                o.orderId AS mandate,
                CONCAT('CN-', cn.id) AS id,
                cn.credit_notes_no AS reference_no,
                cn.customer_id,
                cn.total_amount AS amount,
                3 AS sort_order
            FROM
                ar_credit_notes cn
                LEFT JOIN `order` o ON o.customer_id = cn.customer_id
            WHERE
                cn.issue_date BETWEEN ? AND ?
            UNION ALL
            SELECT
                DATE(ABS.transaction_at) AS date_at,
                LOWER(ABS.type) AS type,
                o.orderId AS mandate,
                CONCAT('PAY-', ABS.id) AS id,
                MD5(
                    CONCAT(
                        TRIM(COALESCE(ABS.curlec_refno, '')),
                        '|',
                        COALESCE(ABS.amount, 0),
                        '|',
                        DATE_FORMAT(ABS.transaction_at, '%Y-%m-%d %H:%i:%s'),
                        '|',
                        TRIM(COALESCE(ABS.description, '')),
                        '|',
                        TRIM(COALESCE(ABS.reference_no, ''))
                    )
                ) AS reference_no,
                ABS.customer_id,
                ABS.amount,
                4 AS sort_order
            FROM
                account_bank_statements ABS
                LEFT JOIN `order` o ON o.customer_id = ABS.customer_id
            WHERE
                ABS.transaction_at BETWEEN ? AND ?
                AND ABS.customer_id IS NOT NULL
            ORDER BY
                date_at ASC,
                sort_order ASC
        ";
    }
}