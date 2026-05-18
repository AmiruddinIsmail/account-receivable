<?php

namespace App\Console\Commands;

use App\Aggregates\AccountAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\LazyCollection;
use Spatie\EventSourcing\Facades\Projectionist;

class ImportLegacyData extends Command
{
    protected $signature = 'account:import-legacy-data {--path=storage/app/legacy-data : Path to the CSV files} {--batch=1000 : Batch size for persistence}';

    protected $description = 'Import legacy invoice, payment, late charge, and credit note data from CSV into event sourcing.';

    public function handle()
    {
        $path = base_path($this->option('path'));
        $batchSize = (int) $this->option('batch');

        if (! File::isDirectory($path)) {
            $this->error("Directory not found: {$path}");

            return 1;
        }

        $files = collect(File::files($path))
            ->filter(fn ($file) => $file->getExtension() === 'csv')
            ->sortBy(fn ($file) => $file->getFilename());

        if ($files->isEmpty()) {
            $this->warn("No CSV files found in {$path}");

            return 0;
        }

        $this->info("Found {$files->count()} files. Starting import...");

        $totalRows = 0;
        $aggregates = [];
        $batchCount = 0;

        // $handlers = Projectionist::allEventHandlers()->toArray();
        // Projectionist::withoutEventHandlers();

        try {
            foreach ($files as $file) {
                $this->line("Processing file: {$file->getFilename()}");

                $rows = $this->getCsvRows($file->getPathname());

                $bar = $this->output->createProgressBar();
                $bar->start();

                foreach ($rows as $row) {
                    try {
                        $this->processRow($row, $aggregates);
                        $batchCount++;
                        $totalRows++;
                        $bar->advance();

                        if ($batchCount >= $batchSize) {
                            $this->persistAggregates($aggregates);
                            $aggregates = [];
                            $batchCount = 0;
                        }
                    } catch (\Exception $e) {
                        $this->error("\nError processing row ".json_encode($row).': '.$e->getMessage());
                    }
                }

                // Persist remaining at end of file
                $this->persistAggregates($aggregates);
                $aggregates = [];
                $batchCount = 0;

                $bar->finish();
                $this->line('');
            }
        } finally {
            // Projectionist::addEventHandlers($handlers);
        }

        $this->info("Import completed! Total rows processed: {$totalRows}");

        return 0;
    }

    protected function getCsvRows(string $filePath): LazyCollection
    {
        return LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');
            if (! $handle) {
                return;
            }

            $headers = fgetcsv($handle);
            if (! $headers) {
                fclose($handle);

                return;
            }

            while (($data = fgetcsv($handle)) !== false) {
                if (count($headers) !== count($data)) {
                    continue;
                }
                yield array_combine($headers, $data);
            }

            fclose($handle);
        });
    }

    protected function processRow(array $row, array &$aggregates)
    {
        $mandate = $row['mandate'] ?? null;
        if (! $mandate) {
            return;
        }

        $type = $row['type'] ?? null;
        $date = $row['date'] ?? null;
        $referenceNo = $row['reference_no'] ?? null;
        $amount = $row['amount'] ?? 0;
        $amountCents = (int) round(((float) $amount) * 100);

        if (! isset($aggregates[$mandate])) {
            $aggregates[$mandate] = AccountAggregate::retrieve($mandate);
        }

        $aggregate = $aggregates[$mandate];

        switch ($type) {
            case 'invoice':
                $aggregate->invoiceCreated($referenceNo, $date, $amountCents);
                break;
            case 'payment':
                $aggregate->paymentReceived($referenceNo, $date, $amountCents);
                break;
            case 'lpc':
                // For late charges, extract invoice reference by removing LATE- prefix
                $invoiceNo = str_replace('LATE-', '', $referenceNo);
                $aggregate->lateChargeApplied($referenceNo, $date, $amountCents, $invoiceNo);
                break;
            case 'cn':
                // For credit notes, we don't have an explicit invoice link in CSV
                $aggregate->creditNoteIssued($referenceNo, $date, $amountCents);
                break;
            case 'refund':
                $aggregate->refundIssued($referenceNo, $date, $amountCents);
                break;
            default:
                // Ignore unknown types or 'type' header row if it leaked in
                break;
        }
    }

    protected function persistAggregates(array $aggregates)
    {
        foreach ($aggregates as $aggregate) {
            $aggregate->persist();
        }
    }
}
