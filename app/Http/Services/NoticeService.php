<?php

namespace App\Http\Services;

use App\Models\Notice;
use App\Models\NoticeEvent;
use App\Models\PhotoCompetition;
use App\Models\User;
use Illuminate\Support\Carbon;

class NoticeService extends Service
{
    /** Consecutive dismissals (no click in between) before backing off. */
    public const FATIGUE_AFTER = 3;

    /** Longest a fatigued notice's cooldown can stretch to. */
    public const MAX_COOLDOWN_HOURS = 24 * 30;

    public function __construct(protected NoticeVariables $variables)
    {
        parent::__construct();
    }

    /**
     * The one notice $user should see now, rendered, or null. Global limits
     * (per day, gap between any two) win over everything; then live notices
     * are tried by priority, each against its own audience, cooldown and
     * caps.
     *
     * @return array<string, mixed>|null
     */
    public function next(User $user): ?array
    {
        ['dailyLimit' => $dailyLimit, 'minGapHours' => $minGapHours] = Notice::limits();

        $shown = NoticeEvent::query()
            ->where('user_id', $user->id)
            ->where('action', NoticeEvent::ACTION_SHOWN);

        if ((clone $shown)->where('created_at', '>=', now()->subDay())->count() >= $dailyLimit) {
            return null;
        }

        $lastShownAt = (clone $shown)->max('created_at');

        if ($lastShownAt && Carbon::parse($lastShownAt)->gt(now()->subHours($minGapHours))) {
            return null;
        }

        $notices = Notice::query()->live()->orderByDesc('priority')->oldest()->get();

        foreach ($notices as $notice) {
            if (! $this->matchesAudience($notice, $user)) {
                continue;
            }

            $rendered = $this->render($notice, $user);

            if ($rendered !== null && $this->isDue($notice, $user, $rendered['version'])) {
                return $rendered;
            }
        }

        return null;
    }

    /**
     * $notice's text with variables filled in for $user, plus a version
     * hash of that text. Null if a variable can't be filled.
     *
     * @return array<string, mixed>|null
     */
    public function render(Notice $notice, User $user): ?array
    {
        $title = $this->variables->render($notice->title, $user);
        $body = $this->variables->render($notice->body, $user);
        $primaryLabel = $this->variables->render($notice->primary_label, $user);
        $secondaryLabel = $notice->secondary_label === null
            ? null
            : $this->variables->render($notice->secondary_label, $user);

        if ($title === null || $body === null || $primaryLabel === null
            || ($notice->secondary_label !== null && $secondaryLabel === null)) {
            return null;
        }

        return [
            'id' => $notice->id,
            'version' => substr(sha1(implode("\n", [
                $title,
                $body,
                $primaryLabel,
                $notice->primary_url,
                $secondaryLabel,
            ])), 0, 16),
            'icon' => $notice->icon,
            'title' => $title,
            'body' => $body,
            'primaryLabel' => $primaryLabel,
            'primaryUrl' => $notice->primary_url,
            'secondaryLabel' => $secondaryLabel,
        ];
    }

    public function record(Notice $notice, User $user, string $version, string $action): NoticeEvent
    {
        return NoticeEvent::query()->create([
            'notice_id' => $notice->id,
            'user_id' => $user->id,
            'version' => $version,
            'action' => $action,
        ]);
    }

    private function matchesAudience(Notice $notice, User $user): bool
    {
        return match ($notice->audience) {
            Notice::AUDIENCE_NEVER_REFERRED => ! $user->referralsMade()->exists(),
            Notice::AUDIENCE_NOT_ENTERED => $this->hasNotEnteredActiveCompetition($user),
            default => true,
        };
    }

    private function hasNotEnteredActiveCompetition(User $user): bool
    {
        $competitionId = PhotoCompetition::query()->active()->value('id');

        return $competitionId !== null
            && ! $user->photos()->where('competition_id', $competitionId)->exists();
    }

    /**
     * Per-notice rules, all scoped to $version so new text (e.g. a new
     * prize) starts over.
     */
    private function isDue(Notice $notice, User $user, string $version): bool
    {
        $events = NoticeEvent::query()
            ->where('notice_id', $notice->id)
            ->where('user_id', $user->id)
            ->where('version', $version)
            ->latest('created_at')
            ->get(['action', 'created_at']);

        if ($notice->stop_after_click && $events->contains('action', NoticeEvent::ACTION_PRIMARY_CLICK)) {
            return false;
        }

        $impressions = $events->where('action', NoticeEvent::ACTION_SHOWN);

        if ($notice->max_impressions !== null && $impressions->count() >= $notice->max_impressions) {
            return false;
        }

        $lastShown = $impressions->first();

        if ($lastShown === null) {
            return true;
        }

        return $lastShown->created_at->lte(now()->subHours($this->cooldownHours($notice, $events)));
    }

    /**
     * The notice's own cooldown, doubled for every dismissal past
     * FATIGUE_AFTER since the user last clicked through, so people who
     * clearly aren't interested see it less and less.
     *
     * @param  \Illuminate\Support\Collection<int, NoticeEvent>  $events  newest first
     */
    private function cooldownHours(Notice $notice, $events): int
    {
        $dismissals = 0;

        foreach ($events as $event) {
            if ($event->action === NoticeEvent::ACTION_PRIMARY_CLICK) {
                break;
            }

            if (in_array($event->action, [NoticeEvent::ACTION_DISMISSED, NoticeEvent::ACTION_SECONDARY_CLICK], true)) {
                $dismissals++;
            }
        }

        $extra = max(0, $dismissals - self::FATIGUE_AFTER + 1);

        return (int) min($notice->cooldown_hours * (2 ** $extra), self::MAX_COOLDOWN_HOURS);
    }
}
