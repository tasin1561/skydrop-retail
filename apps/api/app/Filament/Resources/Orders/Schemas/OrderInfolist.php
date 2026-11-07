<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Admin;
use App\Filament\Support\FulfilmentOrderSection;
use App\Models\Order;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)->columnSpan(2)->schema([
                    FulfilmentOrderSection::make(), // fulfilment: price alerts, hub, unboxing, returns
                    Section::make('Summary')->columns(4)->schema([
                        TextEntry::make('status')->badge()
                            ->formatStateUsing(fn ($state) => Admin::ORDER_STATUS_LABELS[$state] ?? $state)
                            ->color(fn ($state) => Admin::ORDER_STATUS_COLORS[$state] ?? 'gray'),
                        TextEntry::make('payment_status')->label('Payment')->badge()
                            ->color(fn ($state) => Admin::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                        TextEntry::make('total_bdt')->label('Total')->formatStateUsing(fn ($state) => Admin::bdt($state))->weight('bold'),
                        TextEntry::make('tracking_code')->label('Tracking')->copyable()->placeholder('not yet'),
                    ]),
                    Section::make('Items by store')
                        ->description('Links open the product on the source store so you can buy it.')
                        ->schema([View::make('filament.admin.order-items')]),
                    Section::make('Payments')->schema([
                        RepeatableEntry::make('payments')->hiddenLabel()
                            ->table([
                                TableColumn::make('Gateway'), TableColumn::make('Kind'), TableColumn::make('Amount'),
                                TableColumn::make('Status'), TableColumn::make('Ref'), TableColumn::make('Refunded'), TableColumn::make('When'),
                            ])
                            ->schema([
                                TextEntry::make('gateway')->badge()->label('Gateway'),
                                TextEntry::make('kind')->label('Kind')->placeholder('full'),
                                TextEntry::make('amount_bdt')->label('Amount')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                                TextEntry::make('status')->label('Status')->badge()->color(fn ($state) => Admin::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                                TextEntry::make('gateway_ref')->label('Ref')->placeholder('—')->copyable(),
                                TextEntry::make('refunded_bdt')->label('Refunded')->formatStateUsing(fn ($state) => $state > 0 ? Admin::bdt($state) : '—'),
                                TextEntry::make('created_at')->since()->label('When'),
                            ])
                            ->placeholder('No payments yet'),
                    ]),
                    Section::make('Price verifications')->collapsible()->schema([
                        RepeatableEntry::make('verifications')->hiddenLabel()
                            ->table([
                                TableColumn::make('Product'), TableColumn::make('Seen'), TableColumn::make('Fresh'),
                                TableColumn::make('Deliverable'), TableColumn::make('OK'), TableColumn::make('Provider'), TableColumn::make('When'),
                            ])
                            ->schema([
                                TextEntry::make('product_id')->label('Product'),
                                TextEntry::make('seen_bdt')->label('Seen')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                                TextEntry::make('fresh_bdt')->label('Fresh')->formatStateUsing(fn ($state) => $state !== null ? Admin::bdt($state) : '—')
                                    ->color(fn ($state, $record) => $state !== null && $state > $record->seen_bdt ? 'danger' : null),
                                IconEntry::make('deliverable')->label('Deliverable')->boolean(),
                                IconEntry::make('ok')->label('OK')->boolean(),
                                TextEntry::make('provider')->label('Provider')->placeholder('—'),
                                TextEntry::make('verified_at')->label('When')->since(),
                            ])
                            ->placeholder('No verifications recorded'),
                    ]),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Customer')->schema([
                        TextEntry::make('user.name')->label('Name')
                            ->url(fn (Order $r) => $r->user_id ? UserResource::getUrl('view', ['record' => $r->user_id]) : null),
                        TextEntry::make('user.phone')->label('Phone')->copyable()->placeholder('—'),
                        TextEntry::make('user.email')->label('Email')->placeholder('—'),
                        TextEntry::make('customer_orders')->label('Orders by this customer')
                            ->state(fn (Order $r) => Order::query()->where('user_id', $r->user_id)->count()),
                    ]),
                    Section::make('Delivery address')->schema([
                        TextEntry::make('address_text')->hiddenLabel()
                            ->state(fn (Order $r) => collect([
                                $r->address['name'] ?? null, $r->address['phone'] ?? null, $r->address['line'] ?? null,
                                collect([$r->address['area'] ?? null, $r->address['district'] ?? null, $r->address['division'] ?? null])->filter()->implode(', '),
                            ])->filter()->all())
                            ->listWithLineBreaks(),
                    ]),
                    Section::make('Money')->columns(2)->schema([
                        TextEntry::make('subtotal_bdt')->label('Subtotal')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                        TextEntry::make('shipping_bdt')->label('Shipping')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                        TextEntry::make('discount_bdt')->label('Discount')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                        TextEntry::make('coupon_code')->label('Coupon')->placeholder('—'),
                        TextEntry::make('paid_bdt')->label('Paid')->formatStateUsing(fn ($state) => Admin::bdt($state))->color('success'),
                        TextEntry::make('due')->label('Due')->state(fn (Order $r) => Admin::bdt(max(0, $r->total_bdt - $r->paid_bdt))),
                        TextEntry::make('payment_method')->label('Method')
                            ->formatStateUsing(fn (Order $r) => method_exists($r, 'paymentLabel') ? $r->paymentLabel() : $r->payment_method),
                        TextEntry::make('fx_rate')->label('FX at order')->numeric(4),
                        TextEntry::make('cost_inr')->label('Cost in India')
                            ->state(fn (Order $r) => '₹'.number_format($r->items->sum(fn ($i) => $i->unit_inr * $i->qty))),
                        TextEntry::make('points_redeemed')->label('Points used')->placeholder('0'),
                    ]),
                    Section::make('Skydrop')->schema([
                        TextEntry::make('skydrop_consignment_id')->label('Consignment')->copyable()->placeholder('not created'),
                    ]),
                    OrderFraudSection::make(), // fraud module
                    OrderProfitSection::make(), // finance module
                    OrderAttributionSection::make(),
                    Section::make('Staff notes')->schema([
                        TextEntry::make('staff_notes')->hiddenLabel()->placeholder('No notes')->prose(),
                        TextEntry::make('notes')->label('Customer note')->placeholder('—'),
                    ]),
                    Section::make('Timeline')->schema([View::make('filament.admin.order-timeline')]),
                ]),
            ]);
    }
}
