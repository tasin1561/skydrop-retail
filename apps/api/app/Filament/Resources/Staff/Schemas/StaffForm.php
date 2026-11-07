<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Filament\Support\Admin;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('phone')->tel()->maxLength(20)->unique(ignoreRecord: true),
                    TextInput::make('password')->password()->revealable()->minLength(10)
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                    Radio::make('role')->options(Admin::ROLES)->default('support')->required()->columnSpanFull()
                        ->descriptions([
                            'admin' => 'Everything, including staff accounts.',
                            'manager' => 'Everything except staff accounts: cancel, refund, delete, points, settings.',
                            'support' => 'Day-to-day: view, verify, buy, ship, notes, reviews, support. No refunds, cancels or deletes.',
                        ]),
                    Hidden::make('is_admin')->default(true),
                ]),
            ]);
    }
}
