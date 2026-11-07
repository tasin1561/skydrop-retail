<?php

namespace Tests\Feature\Ops;

use App\Services\Finance\SettlementMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use OpsTestHelpers, RefreshDatabase;

    public function test_matches_by_trx_id_and_reports_differences(): void
    {
        $a = $this->makeOrder();
        $b = $this->makeOrder();
        $c = $this->makeOrder();
        $this->pay($a, 'bkash', 2600, 'AB12CD34');
        $this->pay($b, 'bkash', 1500, 'EF56GH78');
        $this->pay($c, 'bkash', 999, 'NOTINFILE');
        $date = now()->format('Y-m-d H:i:s');

        $csv = "Date,TrxID,Amount,Fee\n{$date},AB12CD34,2600.00,48.10\n{$date},ef56gh78,1450.00,26.83\n{$date},ZZ999999,700,12\n";
        $r = app(SettlementMatcher::class)->match($csv, 'bkash');

        $this->assertSame(1, $r['totals']['matched']);
        $this->assertSame('AB12CD34', $r['matched'][0]['trx_id']);
        $this->assertSame(1, $r['totals']['mismatched']);
        $this->assertEquals(-50, $r['mismatched'][0]['difference']);
        $this->assertSame('ZZ999999', $r['missing_in_db'][0]['trx_id']);
        $this->assertSame('NOTINFILE', $r['missing_in_file'][0]['trx_id']);
    }

    public function test_sslcommerz_semicolon_file_and_bad_header(): void
    {
        $o = $this->makeOrder();
        $p = $this->pay($o, 'card', 2600, 'SSLVAL1');
        $p->forceFill(['payload' => ['bank_tran_id' => 'BANK777']])->save();
        $r = app(SettlementMatcher::class)->match("tran_date;bank_tran_id;store_amount\n2026-11-01;BANK777;2600\n", 'sslcommerz');
        $this->assertSame(1, $r['totals']['matched']);

        $bad = app(SettlementMatcher::class)->match("foo,bar\n1,2\n");
        $this->assertArrayHasKey('error', $bad);
    }
}
