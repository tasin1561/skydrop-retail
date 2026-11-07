<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\AccountingService;
use App\Services\Finance\CsvExport;
use App\Services\Finance\SettlementMatcher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/** Accounting exports, gateway settlement reconciliation, liabilities and the month-close summary. */
class Accounting extends Page
{
    use HasPagePermission; // RBAC: page.{slug} + the keys below

    /** @var list<string> */
    protected static array $requiredPermissions = ['reports.accounting'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Accounting';

    protected string $view = 'filament.ops.accounting';

    /** @var array<string, mixed> */
    public ?array $filters = [];

    /** Last settlement reconciliation result. @var array<string, mixed>|null */
    public ?array $settlement = null;

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(),
            'month' => now()->subMonthNoOverflow()->format('Y-m'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $months = collect(range(0, 17))->mapWithKeys(fn ($i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => now()->startOfMonth()->subMonths($i)->format('F Y')])->all();

        return $schema->statePath('filters')->columns(3)->components([
            DatePicker::make('from')->label('Exports from')->required()->live(),
            DatePicker::make('to')->label('to')->required()->live(),
            Select::make('month')->label('Month close')->options($months)->required()->live(),
        ]);
    }

    private function range(): array
    {
        return [Carbon::parse($this->filters['from'] ?? now()->startOfMonth()), Carbon::parse($this->filters['to'] ?? now())];
    }

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        $svc = app(AccountingService::class);
        [$from, $to] = $this->range();

        return [
            'gateways' => $svc->paymentsByGateway($from, $to),
            'liabilities' => $svc->liabilities(),
            'close' => $svc->monthClose((string) ($this->filters['month'] ?? now()->format('Y-m'))),
            'settlement' => $this->settlement,
            'xlsx' => CsvExport::xlsxAvailable(),
        ];
    }

    protected function getHeaderActions(): array
    {
        $exports = [
            'journal' => 'Daily sales journal', 'payments' => 'Payments by gateway (trx ids)', 'refunds' => 'Refunds',
            'liabilities' => 'Points & wallet liability', 'gift_cards' => 'Gift card liability', 'affiliates' => 'Affiliate commissions payable',
            'vat' => 'VAT / tax summary', 'close' => 'Month-close summary',
        ];
        $actions = [];
        foreach (array_merge(['csv'], CsvExport::xlsxAvailable() ? ['xlsx'] : []) as $fmt) {
            $actions[] = ActionGroup::make(collect($exports)->map(fn ($label, $key) => Action::make("export_{$key}_{$fmt}")->label($label)
                ->action(fn () => $this->export($key, $fmt)))->values()->all())
                ->label('Export '.strtoupper($fmt))->icon(Heroicon::OutlinedArrowDownTray)->button()->color('gray');
        }

        return [
            Action::make('reconcile')->label('Reconcile settlement')->icon(Heroicon::OutlinedArrowsRightLeft)
                ->modalDescription('Upload a bKash, Nagad or SSLCommerz settlement CSV. Rows are matched to our payments by transaction id.')
                ->schema([
                    Select::make('gateway')->options(['bkash' => 'bKash', 'nagad' => 'Nagad', 'sslcommerz' => 'SSLCommerz (card)'])->placeholder('Any gateway'),
                    TextInput::make('tolerance')->label('Amount tolerance')->prefix('৳')->numeric()->default(0.5),
                    FileUpload::make('file')->label('Settlement CSV')->required()->storeFiles(false)->maxSize(10240)
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/csv']),
                ])
                ->action(function (array $data) {
                    $file = $data['file'] instanceof TemporaryUploadedFile ? $data['file'] : (is_array($data['file']) ? reset($data['file']) : null);
                    $csv = $file instanceof TemporaryUploadedFile ? (string) file_get_contents($file->getRealPath()) : '';
                    $this->settlement = app(SettlementMatcher::class)->match($csv, $data['gateway'] ?? null, (float) ($data['tolerance'] ?? 0.5));
                    ActivityLogger::log('settlement.reconcile', null, [], 'Reconciled '.($data['gateway'] ?? 'any').' settlement', $this->settlement['totals'] ?? []);
                    Notification::make()->title(isset($this->settlement['error']) ? $this->settlement['error'] : 'Settlement reconciled')
                        ->{isset($this->settlement['error']) ? 'danger' : 'success'}()->send();
                }),
            ...$actions,
        ];
    }

    public function export(string $key, string $format = 'csv')
    {
        $svc = app(AccountingService::class);
        [$from, $to] = $this->range();
        [$headers, $rows] = match ($key) {
            'journal' => $svc->salesJournal($from, $to),
            'payments' => $svc->payments($from, $to),
            'refunds' => $svc->refunds($from, $to),
            'liabilities' => [['liability', 'amount_bdt', 'note'], collect($svc->liabilities())->map(fn ($l) => [$l['label'], $l['amount'], $l['note']])->values()->all()],
            'gift_cards' => $svc->giftCards(),
            'affiliates' => $svc->affiliatePayable(),
            'vat' => $svc->vatSummary($from, $to),
            'close' => self::closeRows($svc->monthClose((string) ($this->filters['month'] ?? now()->format('Y-m')))),
        };

        return CsvExport::download('accounting-'.$key, $headers, $rows, $format, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>} */
    public static function closeRows(array $c): array
    {
        $rows = [['Month', $c['month']]];
        foreach ($c['kpis'] as $k => $v) {
            $rows[] = [$k, $v];
        }
        foreach ($c['gateways'] as $g => $v) {
            $rows[] = ["received via {$g}", $v['amount']];
            $rows[] = ["est. fees {$g}", $v['fees']];
        }
        $rows[] = ['refunds', $c['refunds']];
        if (isset($c['gift_card_sales'])) {
            $rows[] = ['gift card sales (added to liability, not revenue)', $c['gift_card_sales']['amount']];
        }
        foreach ($c['liabilities'] as $l) {
            $rows[] = ['liability: '.$l['label'], $l['amount']];
        }
        $rows[] = ['orders with estimated purchase cost', $c['estimated_orders']];

        return [['item', 'value'], $rows];
    }
}
