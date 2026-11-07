<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Support\Rbac\RoleGuard;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /** @var list<string> */
    private array $before = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + RoleForm::split($data['permissions'] ?? []);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->before = $this->record->permissions ?? [];

        return RoleForm::merge($data);
    }

    protected function afterSave(): void
    {
        $after = $this->record->refresh()->permissions ?? [];
        if ($after !== $this->before) {
            RoleGuard::log('roles.permissions_changed', $this->record, ['permissions' => [$this->before, $after],
                'added' => array_values(array_diff($after, $this->before)), 'removed' => array_values(array_diff($this->before, $after))],
                'Changed permissions of role '.$this->record->name);
        }
    }

    protected function getHeaderActions(): array
    {
        return [RoleResource::cloneAction(), DeleteAction::make()];
    }
}
