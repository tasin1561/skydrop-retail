<?php

namespace App\Filament\Resources\AssistantConversations\Schemas;

use App\Models\Assistant\AssistantConversation;
use App\Models\Assistant\AssistantMessage;
use App\Services\Assistant\Pii;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AssistantConversationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('user.name')->label('Shopper')->placeholder('Guest'),
                TextEntry::make('phone')->label('Phone')->state(fn (AssistantConversation $r) => Pii::maskPhone($r->user?->phone)),
                TextEntry::make('client')->badge(),
                TextEntry::make('locale')->badge()->placeholder('—'),
                TextEntry::make('message_count')->label('Messages'),
                TextEntry::make('input_tokens')->label('Input tokens')->numeric(),
                TextEntry::make('output_tokens')->label('Output tokens')->numeric(),
                TextEntry::make('cost_usd')->label('Cost')->money('USD', 4),
            ]),
            Section::make('Transcript')->description('Phone numbers, emails and long numbers were removed before storage.')->columnSpanFull()->schema([
                TextEntry::make('transcript')->hiddenLabel()->state(fn (AssistantConversation $r) => self::transcript($r)),
            ]),
        ]);
    }

    private static function transcript(AssistantConversation $c): HtmlString
    {
        $c->loadMissing('messages.feedback');
        $html = '';
        foreach ($c->messages as $m) {
            /** @var AssistantMessage $m */
            $user = $m->role === 'user';
            $meta = [$m->created_at?->format('d M H:i')];
            if (! $user) {
                $meta[] = $m->model ?: ($m->degraded ? 'basic mode' : null);
                $meta[] = $m->cost_usd > 0 ? '$'.number_format($m->cost_usd, 4) : null;
                $meta[] = $m->tools ? 'tools: '.implode(', ', array_column($m->tools, 'name')) : null;
                $meta[] = $m->error;
                $meta[] = $m->stopped ? 'stopped' : null;
                $r = $m->feedback->sum('rating');
                $meta[] = $m->feedback->isNotEmpty() ? ($r >= 0 ? '👍' : '👎') : null;
                $cards = collect($m->cards ?? [])->map(fn ($e) => $e['type'].(isset($e['data']['items']) ? ' ×'.count($e['data']['items']) : ''))->implode(', ');
                $meta[] = $cards ? 'cards: '.$cards : null;
            }
            $html .= '<div style="margin:.4rem 0;display:flex;justify-content:'.($user ? 'flex-end' : 'flex-start').'">'
                .'<div style="max-width:80%;padding:.55rem .75rem;border-radius:.75rem;white-space:pre-wrap;font-size:.875rem;'
                .($user ? 'background:rgba(11,107,203,.12)' : 'background:rgba(127,127,127,.1)').'">'
                .e($m->content)
                .'<div style="margin-top:.3rem;font-size:.7rem;opacity:.65">'.e(implode(' · ', array_filter($meta))).'</div></div></div>';
        }

        return new HtmlString($html ?: '<em>No messages</em>');
    }
}
