<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Services\Finance\ProfitService;
use App\Support\AdminSecurity;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/** Order view: real profit breakdown (finance module). */
class OrderProfitSection
{
    public static function make(): Section
    {
        return Section::make('Profit')
            ->collapsible()
            ->visible(fn () => AdminSecurity::can('reports.profit'))
            ->schema([
                TextEntry::make('profit_breakdown')->hiddenLabel()->state(fn (Order $r) => self::html(app(ProfitService::class)->forOrder($r)))->html(),
            ]);
    }

    public static function html(array $p): HtmlString
    {
        $row = fn (string $label, float $v, bool $cost = false, string $hint = '') => '<tr><td style="padding:.15rem 0">'.e($label)
            .($hint ? ' <small style="opacity:.6">'.e($hint).'</small>' : '').'</td><td style="text-align:right;white-space:nowrap'.($cost ? ';opacity:.85' : '').'">'
            .($cost ? '−' : '').'৳'.number_format(abs($v)).'</td></tr>';
        $profitColor = $p['profit'] < 0 ? '#e11d48' : '#059669';

        return new HtmlString('<table style="width:100%;font-size:.85rem">'
            .$row('Revenue (charged)', $p['revenue'], false, $p['points_bdt'] > 0 ? 'after ৳'.number_format($p['points_bdt']).' points' : '')
            .$row('Purchase', $p['cogs'], true, '₹'.number_format($p['purchase_inr']).' × '.round($p['fx_paid'], 4).($p['estimated'] ? ' (estimated)' : ''))
            .$row('Skydrop shipping', $p['shipping_cost'], true, round($p['weight_kg'], 2).' kg')
            .$row('Gateway fees', $p['gateway_fees'], true)
            .$row('Data/API share', $p['api_cost'], true)
            .($p['unboxing_cost'] > 0 ? $row('Unboxing', $p['unboxing_cost'], true) : '')
            .($p['refunds'] > 0 ? $row('Refunds', $p['refunds'], true) : '')
            .'<tr><td style="padding-top:.4rem;border-top:1px solid rgba(127,127,127,.25);font-weight:700">Profit</td><td style="padding-top:.4rem;border-top:1px solid rgba(127,127,127,.25);text-align:right;font-weight:700;color:'.$profitColor.'">'
            .($p['profit'] < 0 ? '−' : '').'৳'.number_format(abs($p['profit'])).' ('.$p['margin_pct'].'%)</td></tr>'
            .'<tr><td colspan="2" style="opacity:.65;font-size:.75rem;padding-top:.3rem">FX '.($p['fx_gain'] >= 0 ? 'gain' : 'loss').' ৳'.number_format(abs($p['fx_gain']))
            .' · shipping '.($p['shipping_profit'] >= 0 ? 'profit' : 'loss').' ৳'.number_format(abs($p['shipping_profit'])).'</td></tr></table>');
    }
}
