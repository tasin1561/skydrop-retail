<?php

namespace App\Filament\Resources\AssistantConversations\Pages;

use App\Filament\Resources\AssistantConversations\AssistantConversationResource;
use App\Filament\Widgets\AssistantUsage;
use Filament\Resources\Pages\ListRecords;

class ListAssistantConversations extends ListRecords
{
    protected static string $resource = AssistantConversationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [AssistantUsage::class];
    }
}
