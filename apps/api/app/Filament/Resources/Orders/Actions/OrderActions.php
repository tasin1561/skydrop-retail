<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Support\Admin;
use App\Filament\Support\Ops;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Finance\CsvExport;
use App\Services\Fulfilment\HubService;
use App\Services\Money\WalletPayments;
use App\Support\Features;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** All order operations go through OrderWorkflow (via Ops) so timeline, notifications and points stay consistent. */
class OrderActions
{
    /** @return array<int, Action|ActionGroup> */
    public static function record(): array
    {
        return [
            self::verify(),
            self::markPurchased(),
            self::handToSkydrop(),
            self::advance(),
            ActionGroup::make([
                self::setStatus(),
                self::notes(),
                self::invoice(),
                self::refund(),
                self::cancel(),
                ...FraudActions::record(), // fraud module
            ])->label('More')->icon(Heroicon::EllipsisVertical)->button()->color('gray'),
        ];
    }

    public static function verify(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('info')
            ->visible(fn (Order $record) => Admin::can('orders.update') && $record->status === 'placed')
            ->requiresConfirmation()
            ->modalDescription('Confirm the live price and stock were checked with the source stores (see Verifications).')
            ->schema([Textarea::make('note')->rows(2)->placeholder('Optional note for the timeline')])
            ->action(fn (Order $record, array $data) => self::run(fn () => Ops::transition($record, 'verified', $data['note'] ?? 'Price and stock verified'), 'Order verified'));
    }

    public static function markPurchased(): Action
    {
        return Action::make('markPurchased')
            ->label('Mark purchased')
            ->icon(Heroicon::OutlinedShoppingCart)
            ->color('primary')
            ->visible(fn (Order $record) => Admin::can('orders.mark_purchased') && in_array($record->status, ['placed', 'verified'], true))
            ->modalHeading('Mark items purchased in India')
            ->modalDescription('Enter the marketplace order reference for each item you bought.')
            ->fillForm(fn (Order $record) => ['refs' => $record->items->mapWithKeys(fn ($i) => [$i->id => $i->purchase_ref])->all()])
            ->schema(fn (Order $record) => [
                ...$record->items->groupBy('market')->map(fn ($items, $market) => Section::make(Admin::marketLabel($market))
                    ->compact()
                    ->schema($items->map(fn ($i) => TextInput::make("refs.{$i->id}")
                        ->label(str($i->title)->limit(70).' × '.$i->qty.($i->variant ? " ({$i->variant})" : ''))
                        ->helperText(new HtmlString('<a href="'.e(Admin::sourceUrl($i->market, $i->product_id)).'" target="_blank" rel="noopener" style="color:#5b3df5">Open '.e($i->product_id).' on source ↗</a>'))
                        ->placeholder('e.g. 403-1234567-1234567')
                        ->required())->all()))->values()->all(),
                Textarea::make('note')->rows(2),
            ])
            ->action(fn (Order $record, array $data) => self::run(fn () => Ops::markPurchased($record, $data['refs'] ?? [], $data['note'] ?? null), 'Marked purchased'));
    }

    public static function handToSkydrop(): Action
    {
        return Action::make('handToSkydrop')
            ->label('Hand to Skydrop')
            ->icon(Heroicon::OutlinedTruck)
            ->color('warning')
            ->visible(fn (Order $record) => Admin::can('orders.hand_to_skydrop') && in_array($record->status, ['purchased', 'india_hub'], true)
                && ! HubService::holdsShipping($record)) // fulfilment: unboxing approval pending
            ->requiresConfirmation()
            ->modalDescription(fn (Order $record) => $record->status === 'india_hub'
                ? 'The packed box leaves the Kolkata hub with Skydrop; the order moves to "'.Admin::ORDER_STATUS_LABELS['in_transit'].'".'
                : 'Creates the Skydrop consignment (Kolkata hub → Bangladesh) and assigns a tracking code.')
            ->action(fn (Order $record) => self::run(fn () => Ops::handToSkydrop($record), $record->status === 'india_hub' ? 'Handed to Skydrop: order is crossing the border' : 'Consignment created'));
    }

    public static function advance(): Action
    {
        return Action::make('advance')
            ->label(fn (Order $record) => ($n = Ops::nextStatus($record)) ? 'Move to: '.Admin::ORDER_STATUS_LABELS[$n] : 'Advance')
            ->icon(Heroicon::OutlinedForward)
            ->color('success')
            ->visible(fn (Order $record) => Admin::can('orders.set_status') && $record->stageIndex() >= $record->stageIndex('india_hub') && Ops::nextStatus($record) !== null
                && ! HubService::holdsShipping($record)) // fulfilment: unboxing approval pending
            ->requiresConfirmation()
            ->schema([Textarea::make('note')->rows(2)])
            ->action(fn (Order $record, array $data) => self::run(fn () => Ops::transition($record, Ops::nextStatus($record), $data['note'] ?? null), 'Status updated'));
    }

    public static function setStatus(): Action
    {
        return Action::make('setStatus')
            ->label('Set status…')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn (Order $record) => Admin::can('orders.set_status') && Ops::allowedNext($record) !== [])
            ->schema([
                Select::make('status')->required()
                    ->options(fn (Order $record) => array_intersect_key(Admin::ORDER_STATUS_LABELS, array_flip(Ops::allowedNext($record))))
                    ->helperText('Forward moves only; use this to skip stages (e.g. a missed hub scan).'),
                Textarea::make('note')->rows(2)->required()->helperText('Shown on the timeline; explain manual corrections.'),
            ])
            ->action(fn (Order $record, array $data) => self::run(fn () => Ops::transition($record, $data['status'], $data['note']), 'Status updated'));
    }

    public static function notes(): Action
    {
        return Action::make('notes')
            ->label('Staff notes')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn () => Admin::can('orders.update'))
            ->fillForm(fn (Order $record) => ['staff_notes' => $record->staff_notes])
            ->schema([Textarea::make('staff_notes')->label('Internal notes (never shown to the customer)')->rows(6)])
            ->action(function (Order $record, array $data) {
                $record->forceFill(['staff_notes' => $data['staff_notes']])->save();
                Notification::make()->title('Notes saved')->success()->send();
            });
    }

    public static function invoice(): Action
    {
        return Action::make('invoice')
            ->label('Print invoice')
            ->icon(Heroicon::OutlinedPrinter)
            ->url(fn (Order $record) => route('filament.admin.invoices', ['ids' => $record->id]), true);
    }

    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Refund payment')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Order $record) => Admin::can('orders.refund') && $record->payments()->where('status', 'paid')->exists())
            ->schema(fn (Order $record) => self::refundSchema($record->payments()->where('status', 'paid')->get()))
            ->action(fn (Order $record, array $data) => self::doRefund(Payment::query()->findOrFail($data['payment_id']), $data));
    }

    public static function refundPayment(): Action
    {
        return Action::make('refund')
            ->label('Refund')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Payment $record) => (Admin::can('payments.refund') || Admin::can('orders.refund')) && in_array($record->status, ['paid'], true) && $record->amount_bdt > (float) $record->refunded_bdt)
            ->schema(fn (Payment $record) => self::refundSchema(collect([$record])))
            ->action(fn (Payment $record, array $data) => self::doRefund($record, $data));
    }

    /** @param  \Illuminate\Support\Collection<int, Payment>  $payments */
    private static function refundSchema($payments): array
    {
        $first = $payments->first();

        return [
            Select::make('payment_id')->label('Payment')->required()->default($first?->id)
                ->options($payments->mapWithKeys(fn (Payment $p) => [$p->id => "#{$p->id} {$p->gateway} ".Admin::bdt($p->amount_bdt).' (refunded '.Admin::bdt($p->refunded_bdt).')'])),
            TextInput::make('amount')->label('Amount')->prefix('৳')->numeric()->minValue(1)->required()
                ->default($first ? $first->amount_bdt - (float) $first->refunded_bdt : null),
            TextInput::make('reason')->required()->maxLength(200),
            self::refundToField(), // money module: Skydrop Wallet (instant) vs original method
            TextInput::make('ref')->label('Already refunded in the merchant portal? Enter its reference')->maxLength(100)
                ->helperText('Leave blank to refund through the gateway now.'),
        ];
    }

    /** Money module: "Refund to Skydrop Wallet (instant)" vs the original method; default from Wallet & Plus → Settings. */
    private static function refundToField(): Select
    {
        $bonus = WalletPayments::bonusPct();

        return Select::make('refund_to')->label('Refund to')->required()
            ->visible(fn () => Features::enabled('wallet'))
            ->default(WalletPayments::defaultDestination())
            ->options(['wallet' => 'Skydrop Wallet (instant'.($bonus > 0 ? ', +'.$bonus.'% bonus' : '').')', 'original' => 'Original payment method'])
            ->helperText('Store-credit (wallet) payments always go back to the wallet.');
    }

    private static function doRefund(Payment $payment, array $data): void
    {
        if (($data['refund_to'] ?? null) === 'wallet' || $payment->gateway === WalletPayments::GATEWAY) {
            self::run(fn () => app(WalletPayments::class)->refundPaymentToWallet($payment, (float) $data['amount'], $data['reason']), 'Refunded to Skydrop Wallet');

            return;
        }
        self::run(fn () => Ops::refund($payment, (float) $data['amount'], $data['reason'], $data['ref'] ?? null), 'Refund recorded');
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel order')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record) => Admin::can('orders.cancel') && $record->canCancel())
            ->requiresConfirmation()
            ->modalSubmitActionLabel('Cancel order')->modalCancelActionLabel('Keep order') // "Cancel" next to "Confirm" was ambiguous
            ->modalDescription('Only possible before the items are bought in India. Online payments and redeemed points are refunded automatically.')
            ->schema([Textarea::make('reason')->required()->rows(2), self::refundToField()])
            ->action(function (Order $record, array $data) {
                WalletPayments::prefer($record->id, $data['refund_to'] ?? null); // money: refund destination
                self::run(fn () => Ops::cancel($record, $data['reason']), 'Order cancelled');
                if (! Ops::hasWorkflow()) {
                    foreach ($record->payments()->where('status', 'paid')->get() as $p) {
                        self::run(fn () => Ops::refund($p, $p->amount_bdt - (float) $p->refunded_bdt, 'Order cancelled: '.$data['reason']), 'Refund recorded for payment #'.$p->id);
                    }
                }
            });
    }

    /** @return array<int, BulkAction> */
    public static function bulk(): array
    {
        return [
            BulkAction::make('bulkVerify')->label('Mark verified')->icon(Heroicon::OutlinedShieldCheck)
                ->visible(fn () => Admin::can('orders.update'))
                ->requiresConfirmation()
                ->action(fn (Collection $records) => self::bulkTransition($records->where('status', 'placed'), 'verified', 'Verified (bulk)')),
            BulkAction::make('bulkPurchased')->label('Mark purchased')->icon(Heroicon::OutlinedShoppingCart)
                ->visible(fn () => Admin::can('orders.mark_purchased'))
                ->requiresConfirmation()
                ->modalDescription('Marks verified orders as bought. Add per-item marketplace refs from the Purchase queue.')
                ->action(fn (Collection $records) => self::bulkTransition($records->whereIn('status', ['placed', 'verified']), 'purchased', 'Bought from the source stores (bulk)')),
            BulkAction::make('bulkInvoices')->label('Print invoices')->icon(Heroicon::OutlinedPrinter)
                ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(route('filament.admin.invoices', ['ids' => $records->pluck('id')->implode(',')])).', "_blank")')),
            BulkAction::make('exportCsv')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn () => Admin::can('orders.export'))
                ->action(fn (Collection $records) => self::csv($records)),
        ];
    }

    private static function bulkTransition($records, string $status, string $note): void
    {
        $ok = 0;
        $fail = [];
        foreach ($records as $order) {
            try {
                Ops::transition($order, $status, $note);
                $ok++;
            } catch (Throwable $e) {
                $fail[] = $order->id.': '.$e->getMessage();
            }
        }
        Notification::make()->title("{$ok} order(s) updated")->body($fail ? implode("\n", $fail) : null)->{$fail ? 'warning' : 'success'}()->send();
    }

    public static function csv($records): StreamedResponse
    {
        $records = $records instanceof Collection ? $records->load(['user', 'items']) : $records;

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['order_id', 'created_at', 'status', 'customer', 'phone', 'district', 'payment_method', 'payment_status', 'items', 'markets', 'subtotal_bdt', 'shipping_bdt', 'discount_bdt', 'total_bdt', 'paid_bdt', 'tracking_code']);
            foreach ($records as $o) {
                fputcsv($out, array_map(fn ($v) => CsvExport::safe($v), [
                    $o->id, $o->created_at?->toDateTimeString(), $o->status, $o->user?->name ?? ($o->address['name'] ?? ''), $o->user?->phone ?? ($o->address['phone'] ?? ''),
                    $o->address['district'] ?? '', $o->payment_method, $o->payment_status, $o->items->sum('qty'), $o->items->pluck('market')->unique()->implode('+'),
                    $o->subtotal_bdt, $o->shipping_bdt, $o->discount_bdt, $o->total_bdt, $o->paid_bdt, $o->tracking_code,
                ]));
            }
            fclose($out);
        }, 'skydrop-orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private static function run(callable $fn, string $success): void
    {
        try {
            $fn();
            Notification::make()->title($success)->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Action failed')->body($e->getMessage())->danger()->persistent()->send();
        }
    }
}
