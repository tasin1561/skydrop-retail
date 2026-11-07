<?php

namespace App\Filament\Resources\HubTasks\Pages;

use App\Filament\Resources\HubTasks\HubTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListHubTasks extends ListRecords
{
    protected static string $resource = HubTaskResource::class;
}
