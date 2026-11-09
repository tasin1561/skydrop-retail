<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Support\SvgChart;
use App\Services\Catalog\DataUsage as Usage;
use App\Services\Finance\CsvExport;
use App\Support\BizTime;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * System → Product data usage: successful (billable) product-data calls against the monthly plan, failed calls and
 * retries shown apart, per day and per purpose, for any billing cycle, with CSV export. Plan sizes and the alert level
 * live in System → Limits ("Product data plan"). Needs page.data-usage (Super Admin, Manager, Finance).
 */
class DataUsage extends Page
{
    use HasPagePermission;

    protected static ?string $slug = 'data-usage';

    protected static ?string $title = 'Product data usage';

    protected static ?string $navigationLabel = 'Product data usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.system.data-usage';

    /** @var array<string, mixed> */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill(['cycle' => app(Usage::class)->cycle()['key']]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filters')->components([
            Select::make('cycle')->label('Billing cycle')->options(fn () => app(Usage::class)->cycleOptions())
                ->selectablePlaceholder(false)->live()->native(false),
        ]);
    }

    /** @return array{start: Carbon, end: Carbon, key: string, label: string} */
    public function selectedCycle(): array
    {
        $usage = app(Usage::class);
        $key = (string) ($this->filters['cycle'] ?? '');

        return $key !== '' ? $usage->cycleFor($key) : $usage->cycle();
    }

    /** @return array<string, mixed> data for the blade view */
    public function viewData(): array
    {
        $usage = app(Usage::class);
        $r = $usage->report($this->selectedCycle());
        $current = $r['cycle']['key'] === $usage->cycle()['key'];

        return [
            'r' => $r,
            'current' => $current,
            'chart' => SvgChart::usageDaily($r['days'], now(BizTime::TZ)->toDateString()),
            'colours' => SvgChart::usageColours(),
            'banner' => $this->banner($r),
        ];
    }

    /** @param array<string, mixed> $r @return array{level: string, text: string}|null */
    private function banner(array $r): ?array
    {
        if ($r['capacity']['amazon'] <= 0) {
            return ['level' => 'info', 'text' => 'No monthly plan is set. Set the plan size in System → Limits → Product data plan.'];
        }
        if ($r['pct'] >= 100) {
            return ['level' => 'danger', 'text' => sprintf('The monthly plan is used up (%s%% of %s). Successful calls from here on are over the plan.',
                number_format($r['pct'], 1), $r['cycle']['label'])];
        }
        if ($r['pct'] >= $r['capacity']['alert']) {
            return ['level' => 'warning', 'text' => sprintf('Usage has passed %d%% of the monthly plan (%s%% used). About %s Amazon calls or %s Flipkart calls are left.',
                $r['capacity']['alert'], number_format($r['pct'], 1), number_format($r['remaining_amazon']), number_format($r['remaining_flipkart']))];
        }
        if (! $r['over'] && $r['started'] && $r['projected_pct'] > 100) {
            return ['level' => 'info', 'text' => sprintf('At the current daily rate this cycle would end at about %s%% of the plan.', number_format($r['projected_pct']))];
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->export()),
        ];
    }

    public function export()
    {
        abort_unless(static::canAccess(), 403);
        $cycle = $this->selectedCycle();
        [$headers, $rows] = app(Usage::class)->csv($cycle);

        return CsvExport::download('product-data-usage-'.$cycle['key'], $headers, $rows, 'csv', ['cycle' => $cycle['label']]);
    }
}
