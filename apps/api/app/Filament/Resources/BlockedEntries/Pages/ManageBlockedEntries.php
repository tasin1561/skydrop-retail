<?php

namespace App\Filament\Resources\BlockedEntries\Pages;

use App\Filament\Resources\BlockedEntries\BlockedEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedEntries extends ManageRecords
{
    protected static string $resource = BlockedEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['source' => 'staff', 'created_by' => auth()->id()])];
    }
}
