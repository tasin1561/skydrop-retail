<?php

namespace App\Services\Finance;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Reconciles a gateway settlement CSV (bKash merchant statement, SSLCommerz settlement report, Nagad) with our
 * payments by transaction id. Column names are detected from the header row.
 */
class SettlementMatcher
{
    private const TRX = ['trxid', 'trx_id', 'transaction_id', 'transactionid', 'transaction id', 'trx id', 'bank_tran_id', 'tran_id', 'txn_id', 'payment_ref', 'reference'];

    private const AMOUNT = ['amount', 'trans_amount', 'transaction_amount', 'total_amount', 'currency_amount', 'paid_amount', 'store_amount'];

    private const FEE = ['fee', 'charge', 'commission', 'mdr', 'gateway_fee', 'service_charge'];

    private const DATE = ['date', 'trx_date', 'tran_date', 'transaction_date', 'datetime', 'created_at', 'completed_time'];

    /**
     * @return array{matched: array<int, array>, mismatched: array<int, array>, missing_in_db: array<int, array>,
     *               missing_in_file: array<int, array>, totals: array<string, float|int>, error?: string}
     */
    public function match(string $csv, ?string $gateway = null, float $tolerance = 0.5): array
    {
        $lines = array_values(array_filter(preg_split('/\r\n|\n|\r/', ltrim($csv, "\xEF\xBB\xBF")) ?: [], fn ($l) => trim($l) !== ''));
        $out = ['matched' => [], 'mismatched' => [], 'missing_in_db' => [], 'missing_in_file' => [], 'totals' => []];
        if (count($lines) < 2) {
            return $out + ['error' => 'The file has no data rows.'];
        }
        $delim = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv($lines[0], $delim, '"', '\\'));
        $col = fn (array $names) => collect($header)->search(fn ($h) => in_array(str_replace(' ', '_', $h), array_map(fn ($n) => str_replace(' ', '_', $n), $names), true));
        $iTrx = $col(self::TRX);
        $iAmt = $col(self::AMOUNT);
        $iFee = $col(self::FEE);
        $iDate = $col(self::DATE);
        if ($iTrx === false || $iAmt === false) {
            return $out + ['error' => 'Could not find the transaction id and amount columns (header: '.implode(', ', $header).').'];
        }

        $payments = Payment::query()->whereIn('status', ['paid', 'refunded'])->when($gateway, fn ($q) => $q->whereIn('gateway', $gateway === 'sslcommerz' ? ['sslcommerz', 'card'] : [$gateway]))->get();
        $byTrx = [];
        foreach ($payments as $p) {
            foreach (array_filter([$p->gateway_ref, $p->payload['trxID'] ?? null, $p->payload['bank_tran_id'] ?? null, $p->payload['tran_id'] ?? null]) as $ref) {
                $byTrx[strtoupper(trim((string) $ref))] = $p;
            }
        }

        $seen = [];
        $dates = [];
        foreach (array_slice($lines, 1) as $line) {
            $c = str_getcsv($line, $delim, '"', '\\');
            $trx = strtoupper(trim((string) ($c[$iTrx] ?? '')));
            if ($trx === '') {
                continue;
            }
            $amount = (float) preg_replace('/[^\d.\-]/', '', (string) ($c[$iAmt] ?? '0'));
            $fee = $iFee !== false ? (float) preg_replace('/[^\d.\-]/', '', (string) ($c[$iFee] ?? '0')) : null;
            if ($iDate !== false && ($d = rescue(fn () => Carbon::parse((string) $c[$iDate]), null, false))) {
                $dates[] = $d;
            }
            $row = ['trx_id' => $trx, 'file_amount' => $amount, 'fee' => $fee];
            $p = $byTrx[$trx] ?? null;
            if (! $p) {
                $out['missing_in_db'][] = $row;

                continue;
            }
            $seen[$p->id] = true;
            $row += ['order_id' => $p->order_id, 'payment_id' => $p->id, 'our_amount' => (float) $p->amount_bdt, 'gateway' => $p->gateway];
            $diff = abs($amount - (float) $p->amount_bdt);
            if ($diff > $tolerance) {
                $out['mismatched'][] = $row + ['difference' => round($amount - (float) $p->amount_bdt, 2)];
            } else {
                $out['matched'][] = $row;
            }
        }

        // Our paid payments inside the file's date range that the file does not mention.
        if ($dates) {
            $min = min($dates)->copy()->startOfDay();
            $max = max($dates)->copy()->endOfDay();
            foreach ($payments as $p) {
                $at = $p->paid_at ?? $p->created_at;
                if (! isset($seen[$p->id]) && $at && $at->betweenIncluded($min, $max)) {
                    $out['missing_in_file'][] = ['trx_id' => AccountingService::trxId($p), 'order_id' => $p->order_id, 'payment_id' => $p->id,
                        'our_amount' => (float) $p->amount_bdt, 'gateway' => $p->gateway, 'paid_at' => $at->toDateTimeString()];
                }
            }
        }

        $out['totals'] = [
            'rows' => count($lines) - 1, 'matched' => count($out['matched']), 'mismatched' => count($out['mismatched']),
            'missing_in_db' => count($out['missing_in_db']), 'missing_in_file' => count($out['missing_in_file']),
            'file_amount' => round(array_sum(array_column([...$out['matched'], ...$out['mismatched'], ...$out['missing_in_db']], 'file_amount')), 2),
        ];

        return $out;
    }
}
