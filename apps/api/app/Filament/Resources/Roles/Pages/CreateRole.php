<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Support\Rbac\RoleGuard;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RoleForm::merge($data) + ['is_system' => false];
    }

    protected function afterCreate(): void
    {
        RoleGuard::log('roles.created', $this->record, ['permissions' => [[], $this->record->permissions]], 'Created role '.$this->record->name);
    }
}
