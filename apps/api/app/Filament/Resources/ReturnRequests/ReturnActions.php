<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Support\Admin;
use App\Filament\Support\FulfilmentAccess;
use App\Models\Fulfilment\ReturnRequest;
use App\Services\Fulfilment\ReturnService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;

/** Return case actions; each goes through ReturnService (timeline + customer SMS). Guarded by "returns.resolve". */
class ReturnActions
{
    /** @return array<int, Action|ActionGroup> */
    public static function all(): array
    {
        return [
            self::make('approve', 'Approve', Heroicon::OutlinedCheck, 'success', 'approved',
                [Textarea::make('note')->rows(2)->placeholder('Optional note for the customer')],
                fn (ReturnRequest $r, array $d, ReturnService $s) => $s->approve($r, $d['note'] ?? null)),
            self::make('reject', 'Reject', Heroicon::OutlinedXMark, 'danger', 'rejected',
                [Textarea::make('note')->label('Reason (sent to the customer)')->rows(2)->required()],
                fn (ReturnRequest $r, array $d, ReturnService $s) => $s->reject($r, $d['note'])),
            self::make('pickup', 'Schedule pickup', Heroicon::OutlinedTruck, 'info', 'pickup_scheduled',
                [DateTimePicker::make('at')->label('Pickup')->required()->native(false)->minDate(now()->startOfDay())->default(now()->addDay()->setTime(11, 0)),
                    Textarea::make('note')->rows(2)->helperText('Skydrop reverse pickup is booked manually for now.')],
                fn (ReturnRequest $r, array $d, ReturnService $s) => $s->schedulePickup($r, Carbon::parse($d['at']), $d['note'] ?? null)),
            self::make('received', 'Mark received', Heroicon::OutlinedInboxArrowDown, 'gray', 'received',
                [Textarea::make('note')->rows(2)->placeholder('Condition of the returned item')],
                fn (ReturnRequest $r, array $d, ReturnService $s) => $s->markReceived($r, $d['note'] ?? null)),
            ActionGroup::make([
                self::make('refund', 'Refund', Heroicon::OutlinedReceiptRefund, 'danger', 'refunded',
                    [TextInput::make('amount')->prefix('৳')->numeric()->minValue(1)->required()->default(fn (ReturnRequest $r) => $r->refund_bdt),
                        Textarea::make('note')->rows(2)],
                    fn (ReturnRequest $r, array $d, ReturnService $s) => $s->refund($r, (float) $d['amount'], $d['note'] ?? null)),
                self::make('credit', 'Store credit', Heroicon::OutlinedWallet, 'warning', 'credited',
                    [TextInput::make('amount')->prefix('৳')->numeric()->minValue(1)->required()->default(fn (ReturnRequest $r) => $r->refund_bdt),
                        Textarea::make('note')->rows(2)],
                    fn (ReturnRequest $r, array $d, ReturnService $s) => $s->credit($r, (float) $d['amount'], $d['note'] ?? null),
                    fn () => app(ReturnService::class)->walletAvailable()),
                self::make('replace', 'Create replacement', Heroicon::OutlinedArrowPath, 'primary', 'replaced',
                    [Textarea::make('note')->rows(2)->helperText('Creates a new order at no charge, linked to this one, ready in the purchase queue.')],
                    fn (ReturnRequest $r, array $d, ReturnService $s) => $s->replace($r, $d['note'] ?? null)),
            ])->label('Resolve')->icon(Heroicon::EllipsisVertical)->button()->color('gray'),
            Action::make('staffNotes')->label('Staff notes')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                ->visible(fn () => FulfilmentAccess::returns())
                ->fillForm(fn (ReturnRequest $r) => ['staff_notes' => $r->staff_notes])
                ->schema([Textarea::make('staff_notes')->label('Internal notes')->rows(5)])
                ->action(function (ReturnRequest $record, array $data) {
                    $record->forceFill(['staff_notes' => $data['staff_notes']])->save();
                    Notification::make()->title('Notes saved')->success()->send();
                }),
        ];
    }

    private static function make(string $name, string $label, $icon, string $color, string $to, array $schema, callable $do, ?callable $extraVisible = null): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)
            ->visible(fn (ReturnRequest $record) => FulfilmentAccess::returns() && app(ReturnService::class)->canMove($record, $to) && (! $extraVisible || $extraVisible()))
            ->schema($schema)
            ->modalHeading(fn (ReturnRequest $record) => "{$label}: return #{$record->id} ({$record->order_id})")
            ->action(function (ReturnRequest $record, array $data) use ($do, $label) {
                try {
                    $do($record, $data, app(ReturnService::class));
                    Notification::make()->title($label.' done')->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title($label.' failed')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    public static function statusColor(?string $s): string
    {
        return match ($s) {
            'requested' => 'warning', 'approved', 'pickup_scheduled' => 'info', 'received' => 'primary', 'rejected' => 'danger', default => 'success',
        };
    }

    public static function bdt(float $v): string
    {
        return Admin::bdt($v);
    }
}
