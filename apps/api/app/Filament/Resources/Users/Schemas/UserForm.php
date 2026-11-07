<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(20)->unique(ignoreRecord: true)
                        ->regex('/^\+8801[3-9]\d{8}$/')->helperText('Format +8801XXXXXXXXX'),
                    TextInput::make('email')->email()->maxLength(255)->unique(ignoreRecord: true),
                ]),
            ]);
    }
}
