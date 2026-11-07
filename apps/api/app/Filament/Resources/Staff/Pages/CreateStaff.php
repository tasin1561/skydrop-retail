<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $state = $this->form->getRawState();

        return DB::transaction(function () use ($data, $state) {
            $user = new User;
            $user->forceFill($data + ['is_admin' => true, 'role' => 'support', 'blocked_at' => ! empty($state['blocked']) ? now() : null])->save();
            $user->syncRoles($state['role_ids'] ?? []);

            return $user;
        });
    }
}
