<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\Admin;
use App\Models\Order;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Section::make('Profile')->columnSpan(2)->columns(2)->schema([
                    TextEntry::make('name')->weight('bold'),
                    TextEntry::make('phone')->copyable()->placeholder('—'),
                    TextEntry::make('email')->placeholder('—'),
                    TextEntry::make('created_at')->label('Joined')->date(),
                    TextEntry::make('phone_verified_at')->label('Phone verified')->since()->placeholder('no'),
                    TextEntry::make('blocked_at')->label('Account')->badge()
                        ->state(fn (User $r) => $r->blocked_at ? 'Blocked '.\Illuminate\Support\Carbon::parse($r->blocked_at)->diffForHumans() : 'Active')
                        ->color(fn (User $r) => $r->blocked_at ? 'danger' : 'success'),
                ]),
                Section::make('Value')->columnSpan(2)->columns(3)->schema([
                    TextEntry::make('orders_total')->label('Orders')->state(fn (User $r) => Order::query()->where('user_id', $r->id)->count()),
                    TextEntry::make('spent')->label('Total spent')
                        ->state(fn (User $r) => Admin::bdt(Order::query()->where('user_id', $r->id)->where('status', '!=', 'cancelled')->sum('total_bdt'))),
                    TextEntry::make('points_balance')->label('Points balance')->numeric()->weight('bold')->color('primary')->placeholder('0'),
                    TextEntry::make('wishlist')->label('Wishlist')->state(fn (User $r) => self::count('wishlist_items', $r)),
                    TextEntry::make('reviews_n')->label('Reviews')->state(fn (User $r) => self::count('product_reviews', $r)),
                    TextEntry::make('support_n')->label('Support requests')->state(fn (User $r) => self::count('support_requests', $r)),
                ]),
                Section::make('Addresses')->columnSpanFull()->collapsible()->schema([
                    TextEntry::make('addresses_list')->hiddenLabel()
                        ->state(fn (User $r) => $r->addresses()->get()->map(fn ($a) => ($a->is_default ? '★ ' : '').
                            "{$a->name}, {$a->phone} — {$a->line}, {$a->area}, {$a->district}, {$a->division}")->all())
                        ->listWithLineBreaks()->bulleted()->placeholder('No saved addresses'),
                ]),
            ]);
    }

    private static function count(string $table, User $r): int
    {
        return DbSchema::hasTable($table) ? DB::table($table)->where('user_id', $r->id)->count() : 0;
    }
}
