<?php

namespace Tests\Feature\Catalog;

use App\Services\Catalog\BudgetGuard;
use App\Support\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Daily ceiling for Oxylabs checkout price checks (System → Limits). */
class VerifyCeilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_checks_stop_at_the_daily_ceiling_and_reset_next_day(): void
    {
        $g = app(BudgetGuard::class);
        $this->assertSame(3.0, $g->verifyCap(), 'default ceiling is $3/day');
        $this->assertTrue($g->allowsMetered('verify'));

        $g->recordVerifySpend(2.999);
        $this->assertTrue($g->allowsMetered('verify'));
        $g->recordVerifySpend(0.002);
        $this->assertFalse($g->allowsMetered('verify'), 'past the ceiling checkout uses recent prices instead');
        $this->assertTrue($g->allowsMetered('browse'), 'browsing has its own daily cap');

        $this->travel(1)->days();
        $this->assertTrue($g->allowsMetered('verify'), 'new day, new ceiling');
    }

    public function test_zero_means_no_ceiling(): void
    {
        Limits::set('catalog.verify_daily_usd', 0);
        $g = app(BudgetGuard::class);
        $g->recordVerifySpend(500);
        $this->assertTrue($g->allowsMetered('verify'));
    }
}
