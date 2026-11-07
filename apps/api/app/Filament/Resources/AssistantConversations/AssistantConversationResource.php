<?php

namespace App\Filament\Resources\AssistantConversations;

use App\Filament\Resources\AssistantConversations\Pages\ListAssistantConversations;
use App\Filament\Resources\AssistantConversations\Pages\ViewAssistantConversation;
use App\Filament\Resources\AssistantConversations\Schemas\AssistantConversationInfolist;
use App\Filament\Resources\AssistantConversations\Tables\AssistantConversationsTable;
use App\Filament\Support\Admin;
use App\Models\Assistant\AssistantConversation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Read-only browser of AI assistant conversations (PII-scrubbed text, masked phones). Permission: assistant.view. */
class AssistantConversationResource extends Resource
{
    protected static ?string $model = AssistantConversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'AI conversations';

    protected static ?string $modelLabel = 'AI conversation';

    protected static ?string $slug = 'assistant-conversations';

    public static function allowed(): bool
    {
        return method_exists(Admin::class, 'can') ? Admin::can('assistant.view') : Admin::canManage();
    }

    public static function canAccess(): bool
    {
        return self::allowed();
    }

    public static function canViewAny(): bool
    {
        return self::allowed();
    }

    public static function canView(Model $record): bool
    {
        return self::allowed();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssistantConversationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssistantConversationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssistantConversations::route('/'),
            'view' => ViewAssistantConversation::route('/{record}'),
        ];
    }
}
