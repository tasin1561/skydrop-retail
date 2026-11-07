<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Support\Rbac\RoleGuard;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $state = $this->form->getRawState();
        try {
            DB::transaction(function () use ($record, $data, $state) {
                $blocking = ! empty($state['blocked']);
                if ($blocking && ! $record->blocked_at) {
                    RoleGuard::assertCanRemove($record);
                }
                $record->forceFill($data + ['blocked_at' => $blocking ? ($record->blocked_at ?? now()) : null])->save();
                $record->syncRoles($state['role_ids'] ?? []);
            });
        } catch (RuntimeException $e) {
            Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [StaffResource::previewAction(), DeleteAction::make()];
    }
}
