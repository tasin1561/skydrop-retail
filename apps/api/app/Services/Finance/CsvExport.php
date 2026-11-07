<?php

namespace App\Services\Finance;

use App\Services\Audit\ActivityLogger;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV (always) or XLSX (when openspout is installed) download of [headers, rows]; every export is logged. */
class CsvExport
{
    public static function xlsxAvailable(): bool
    {
        return class_exists(XlsxWriter::class) && class_exists(Row::class);
    }

    /** @param  array<int, string>  $headers  @param  array<int, array<int, mixed>>  $rows */
    public static function download(string $name, array $headers, array $rows, string $format = 'csv', array $meta = []): StreamedResponse
    {
        $format = $format === 'xlsx' && self::xlsxAvailable() ? 'xlsx' : 'csv';
        $file = $name.'-'.now()->format('Ymd-His').'.'.$format;
        ActivityLogger::log('export', null, [], "Exported {$name} ({$format}, ".count($rows).' rows)', $meta + ['file' => $file]);

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $w = new XlsxWriter;
                $w->openToFile('php://output');
                $w->addRow(Row::fromValues($headers));
                foreach ($rows as $r) {
                    $w->addRow(Row::fromValues(array_map(fn ($v) => is_scalar($v) || $v === null ? $v : (string) $v, $r)));
                }
                $w->close();
            }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => self::safe($v), $r), ',', '"', '\\');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @ at the start of text cells). */
    private static function safe(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && ! is_numeric($v)) {
            return "'".$v;
        }

        return $v;
    }
}
