<?php

namespace App\Filament\Pages\Hub;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Support\Admin;
use App\Models\Fulfilment\HubReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Fulfilment\HubService;
use App\Support\Limits;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Throwable;
use UnitEnum;

/**
 * Phone-first receiving at the Skydrop Kolkata hub: scan (BarcodeDetector) or type a marketplace order ref / AWB / our
 * order or item id, then record weight, dimensions, condition, notes and photos. The last item of an order moves it to india_hub.
 */
class HubReceive extends Page
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['hub.receive'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Hub';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Receive parcel';

    protected static ?string $title = 'Receive parcel';

    protected static ?string $slug = 'hub/receive';

    protected string $view = 'filament.hub.receive';

    public string $code = '';

    public ?int $itemId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function getNavigationBadge(): ?string
    {
        $n = OrderItem::query()->whereNull('hub_received_at')->whereNull('cancelled_at')
            ->whereIn('order_id', Order::query()->goods()->select('id')->where('status', 'purchased'))->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Items bought in India, not yet received at the hub';
    }

    public function mount(): void
    {
        $this->form->fill(['condition' => 'ok']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('weight_kg')->label('Weight')->suffix('kg')->numeric()->minValue(0.001)->maxValue(500)->step('any')->required() // 'any': a 0.01 step from min 0.001 rejected 0.12 kg
                    ->inputMode('decimal')->autofocus(),
                Grid::make(['default' => 3])->schema([ // three across even on a phone
                    TextInput::make('length_cm')->label('Length')->suffix('cm')->numeric()->minValue(0)->inputMode('decimal'),
                    TextInput::make('width_cm')->label('Width')->suffix('cm')->numeric()->minValue(0)->inputMode('decimal'),
                    TextInput::make('height_cm')->label('Height')->suffix('cm')->numeric()->minValue(0)->inputMode('decimal'),
                ]),
                ToggleButtons::make('condition')->label('Condition')->inline()->required()
                    ->options(HubReceipt::CONDITIONS)
                    ->colors(['ok' => 'success', 'damaged' => 'danger', 'wrong_item' => 'warning'])
                    ->icons(['ok' => Heroicon::OutlinedCheckCircle, 'damaged' => Heroicon::OutlinedExclamationTriangle, 'wrong_item' => Heroicon::OutlinedArrowsRightLeft]),
                Textarea::make('notes')->rows(2)->maxLength(1000)->placeholder('Optional: what you saw, seal intact, etc.'),
                FileUpload::make('photos')->label('Photos')->multiple()->image()->maxFiles(Limits::int('uploads.max_files'))->maxSize(Limits::int('uploads.image_mb') * 1024)
                    ->disk('public')->directory('hub/'.now()->format('Y/m'))->visibility('public')
                    ->panelLayout('grid')->reorderable()->appendFiles()
                    ->extraInputAttributes(['capture' => 'environment'])
                    ->required(fn () => (bool) $this->selectedOrder()?->unboxing)
                    ->helperText(fn () => $this->selectedOrder()?->unboxing ? 'Paid unboxing: open the parcel and photograph the item from every side. The customer approves these photos.' : null),
                FileUpload::make('video')->label('Short unboxing video (optional)')->disk('public')->directory('hub/video/'.now()->format('Y/m'))
                    ->visibility('public')->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm'])->maxSize(Limits::int('uploads.video_mb') * 1024)
                    ->visible(fn () => (bool) $this->selectedOrder()?->unboxing),
            ]);
    }

    /** @return Collection<int, OrderItem> */
    #[Computed]
    public function matches(): Collection
    {
        return app(HubService::class)->find($this->code);
    }

    /** @return array<string, Order> */
    #[Computed]
    public function orders(): array
    {
        return Order::query()->whereIn('id', $this->matches->pluck('order_id')->unique())->get()->keyBy('id')->all();
    }

    #[Computed]
    public function selected(): ?OrderItem
    {
        return $this->itemId ? OrderItem::query()->find($this->itemId) : null;
    }

    public function selectedOrder(): ?Order
    {
        return $this->selected ? Order::query()->find($this->selected->order_id) : null;
    }

    public function progressFor(string $orderId): array
    {
        $order = Order::query()->find($orderId);

        return $order ? app(HubService::class)->progress($order) : [];
    }

    public function lookup(): void
    {
        $this->code = trim($this->code);
        $this->itemId = null;
        unset($this->matches, $this->orders, $this->selected);
        $open = $this->matches->whereNull('hub_received_at');
        if ($open->count() === 1) {
            $this->pick($open->first()->id); // one match: straight to the form
        } elseif ($this->code !== '' && $this->matches->isEmpty()) {
            Notification::make()->title('No open order item matches “'.$this->code.'”')
                ->body('Try the marketplace order number, the AWB on the label, our order id (SR-…) or item id (IT-…).')->warning()->send();
        }
    }

    public function pick(int $id): void
    {
        $this->itemId = $id;
        unset($this->selected);
        $item = $this->selected;
        $this->form->fill(['condition' => 'ok', 'weight_kg' => $item ? round($item->weight_kg * $item->qty, 3) : null]);
    }

    public function clear(): void
    {
        $this->reset(['code', 'itemId']);
        $this->form->fill(['condition' => 'ok']);
    }

    public function receive(): void
    {
        abort_unless(static::canAccess(), 403);
        $item = $this->selected;
        if (! $item) {
            return;
        }
        $state = $this->form->getState();
        try {
            $receipt = app(HubService::class)->receive($item, $state, Filament::auth()->user());
        } catch (Throwable $e) {
            Notification::make()->title('Not received')->body($e->getMessage())->danger()->send();

            return;
        }
        $order = Order::query()->find($item->order_id);
        $body = $receipt->condition !== 'ok'
            ? 'Discrepancy logged: the customer was told and a return-to-seller task is open.'
            : ($order->status === 'india_hub' ? 'All items are here: order moved to "'.Admin::ORDER_STATUS_LABELS['india_hub'].'".' : null);
        Notification::make()->title("Received item IT-{$item->id} ({$order->id})")->body($body)
            ->{$receipt->condition === 'ok' ? 'success' : 'warning'}()->send();

        $this->itemId = null;
        unset($this->matches, $this->orders, $this->selected);
        $this->form->fill(['condition' => 'ok']);
        $this->dispatch('hub-received');
    }
}
