<?php

namespace App\Filament\Resources\AssistantConversations\Tables;

use App\Models\Assistant\AssistantConversation;
use App\Services\Assistant\Pii;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssistantConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'feedback']))
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('last_message_at')->label('Last message')->dateTime('d M, H:i')->sortable(),
                TextColumn::make('user.name')->label('Shopper')->placeholder('Guest')
                    ->description(fn (AssistantConversation $r) => $r->user ? Pii::maskPhone($r->user->phone) : null),
                TextColumn::make('title')->label('First message')->limit(60)->wrap()->searchable(),
                TextColumn::make('client')->badge()->color('gray'),
                TextColumn::make('locale')->badge()->color('info')->placeholder('—'),
                TextColumn::make('message_count')->label('Msgs')->numeric()->alignEnd()->sortable(),
                TextColumn::make('votes')->label('Feedback')->state(function (AssistantConversation $r) {
                    $up = $r->feedback->where('rating', 1)->count();
                    $down = $r->feedback->where('rating', -1)->count();

                    return trim(($up ? '👍 '.$up.' ' : '').($down ? '👎 '.$down : '')) ?: '—';
                }),
                TextColumn::make('cost_usd')->label('Cost')->money('USD', 4)->sortable()->alignEnd()
                    ->summarize(Sum::make()->label('Total')->money('USD', 2)),
            ])
            ->filters([
                SelectFilter::make('client')->options(['web' => 'Web', 'mobile' => 'Mobile']),
                Filter::make('signed_in')->label('Signed-in shoppers')->query(fn (Builder $query) => $query->whereNotNull('user_id')),
                Filter::make('negative')->label('Has thumbs down')->query(fn (Builder $query) => $query->whereHas('feedback', fn ($f) => $f->where('rating', -1))),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
