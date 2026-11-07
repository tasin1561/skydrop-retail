<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Filament\Resources\Affiliates\AffiliateResource;
use Filament\Resources\Pages\EditRecord;

class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === 'approved' && ! $this->record->approved_at) {
            $data['approved_at'] = now();
        }
        $data['code'] = strtoupper((string) ($data['code'] ?? $this->record->code));

        return $data;
    }
}
