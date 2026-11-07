<?php

namespace App\Filament\Resources\ApiLogs\Pages;

use App\Filament\Resources\ApiLogs\ApiLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewApiLog extends ViewRecord
{
    protected static string $resource = ApiLogResource::class;
}
