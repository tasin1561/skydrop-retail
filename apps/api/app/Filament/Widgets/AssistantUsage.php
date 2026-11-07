<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Admin;
use App\Models\Assistant\AssistantFeedback;
use App\Models\Assistant\AssistantMessage;
use App\Services\Assistant\AssistantSettings;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Throwable;

/** AI assistant spend vs its monthly budget (hard stop), message volume and thumbs-up rate. */
class AssistantUsage extends StatsOverviewWidget
{
    protected static bool $isLazy = true;

    protected static ?int $sort = 8;

    protected ?string $heading = 'AI assistant (Claude API)';

    protected ?string $pollingInterval = '120s';

    public static function canView(): bool
    {
        try {
            return AssistantSettings::enabled() && (method_exists(Admin::class, 'can') ? Admin::can('assistant.view') : Admin::canManage());
        } catch (Throwable) {
            return false;
        }
    }

    protected function getStats(): array
    {
        $start = now()->startOfMonth();
        $spent = AssistantSettings::spentThisMonth();
        $budget = AssistantSettings::budget();
        $pct = $budget > 0 ? $spent / $budget * 100 : 100;
        $daily = collect(range(13, 0))->map(fn ($d) => round((float) AssistantMessage::query()
            ->whereBetween('created_at', [now()->subDays($d)->startOfDay(), now()->subDays($d)->endOfDay()])->sum('cost_usd'), 4))->all();
        $userMsgs = AssistantMessage::query()->where('role', 'user')->where('created_at', '>=', $start);
        $today = (clone $userMsgs)->where('created_at', '>=', now()->startOfDay())->count();
        $month = $userMsgs->count();
        $degraded = AssistantMessage::query()->where('role', 'assistant')->where('created_at', '>=', $start)->where('degraded', true)->count();
        $fb = AssistantFeedback::query()->where('created_at', '>=', $start);
        $votes = (clone $fb)->count();
        $up = $fb->where('rating', 1)->count();
        $model = AssistantSettings::model();

        return [
            Stat::make('Spent this month', '$'.number_format($spent, 2))
                ->description(round($pct, 1).'% of $'.number_format($budget, 0).($pct >= 100 ? ' · stopped: basic search mode' : ''))
                ->descriptionIcon($pct >= 80 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->color($pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'success'))->chart($daily),
            Stat::make('Messages', number_format($today).' today')
                ->description(number_format($month).' this month'.($degraded ? ' · '.$degraded.' basic-mode replies' : '').' · avg $'.number_format($month ? $spent / $month : 0, 4))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight),
            Stat::make('Helpful', $votes ? round($up / $votes * 100).'%' : '—')
                ->description($votes ? "{$up} of {$votes} thumbs up · model ".$model : 'No feedback yet · model '.$model)
                ->icon(Heroicon::OutlinedHandThumbUp)->color($votes && $up / $votes < 0.6 ? 'warning' : 'gray'),
        ];
    }
}
