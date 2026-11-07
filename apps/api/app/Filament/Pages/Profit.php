<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Filament\Support\SvgChart;
use App\Models\Product;
use App\Services\Finance\CsvExport;
use App\Services\Finance\ProfitService;
use App\Support\BizTime;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/** Real profit: revenue vs purchase cost at the FX actually paid, API spend, gateway fees, Skydrop cost, refunds. */
class Profit extends Page
{
    use HasPagePermission; // RBAC: page.{slug} + the keys below

    /** @var list<string> */
    protected static array $requiredPermissions = ['reports.profit'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Profit';

    protected string $view = 'filament.ops.profit';

    /** @var array<string, mixed> */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill(['from' => now(BizTime::TZ)->startOfMonth()->toDateString(), 'to' => now(BizTime::TZ)->toDateString(), 'store' => null, 'category' => null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filters')->columns(4)->components([
            DatePicker::make('from')->required()->live(),
            DatePicker::make('to')->required()->live(),
            Select::make('store')->options(Admin::MARKETS)->placeholder('All stores')->live(),
            Select::make('category')->options(fn () => rescue(fn () => Product::query()->distinct()->orderBy('category')->pluck('category', 'category')->all(), [], false))
                ->searchable()->placeholder('All categories')->live(),
        ]);
    }

    /** @return array<string, mixed> */
    public function report(): array
    {
        $f = $this->filters ?? [];

        return app(ProfitService::class)->report(
            Carbon::parse($f['from'] ?? now(BizTime::TZ)->startOfMonth()), Carbon::parse($f['to'] ?? now(BizTime::TZ)),
            ['store' => $f['store'] ?? null, 'category' => $f['category'] ?? null],
        );
    }

    /** @return array<string, mixed> data for the blade view */
    public function viewData(): array
    {
        $r = $this->report();

        return [
            'k' => $r['kpis'],
            'storeChart' => SvgChart::bars(collect($r['by_store'])->mapWithKeys(fn ($v, $m) => [Admin::marketLabel($m) => $v])->all(), 'profit by store'),
            'categoryChart' => SvgChart::bars($r['by_category'], 'profit by category'),
            'dayChart' => SvgChart::daily($r['by_day']),
            'worst' => $r['orders']->sortBy('margin_pct')->take(15)->values(),
            'orderUrl' => fn (string $id) => OrderResource::getUrl('view', ['record' => $id]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->export('csv')),
            Action::make('xlsx')->label('Export XLSX')->icon(Heroicon::OutlinedTableCells)->color('gray')
                ->visible(CsvExport::xlsxAvailable())
                ->action(fn () => $this->export('xlsx')),
        ];
    }

    public function export(string $format = 'csv')
    {
        $r = $this->report();
        $cols = ['order_id', 'date', 'status', 'items_bdt', 'shipping_bdt', 'fees_bdt', 'discounts_bdt', 'points_bdt', 'revenue', 'purchase_inr', 'fx_order',
            'fx_paid', 'estimated', 'cogs', 'fx_gain', 'api_cost', 'gateway_fees', 'shipping_cost', 'shipping_profit', 'unboxing_cost', 'refunds', 'profit', 'margin_pct'];
        $rows = $r['orders']->map(fn ($o) => array_map(fn ($c) => is_bool($o[$c]) ? ($o[$c] ? 'yes' : 'no') : $o[$c], $cols))->all();

        return CsvExport::download('profit', $cols, $rows, $format, ['filters' => $this->filters]);
    }
}
