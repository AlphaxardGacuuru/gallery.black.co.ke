<?php

namespace App\Services;

use App\Ai\Agents\SignupScreeningAgent;
use App\Events\UserFlaggedEvent;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SignupScreeningService
{
    /**
     * Email stems shorter than this match too many unrelated people.
     */
    private const MIN_STEM_LENGTH = 4;

    public function __construct(protected AiUsageService $aiUsageService) {}

    /**
     * Have the screening agent check a new sign-up and flag them until they
     * add a phone number if the account looks fake or duplicated. Fails
     * open: if the agent errors, the user is left alone.
     */
    public function screen(User $user, ?string $ipAddress): void
    {
        if (! User::screensNewSignups() || ! AiUsage::agentsEnabled()) {
            return;
        }

        // Already has a number (so nothing to ask for), or an admin got to
        // them first.
        if ($user->is_seed || $user->phone || $user->isFlagged()) {
            return;
        }

        try {
            $agent = new SignupScreeningAgent($user, $this->lookAlikes($user, $ipAddress));
            $response = $agent->prompt($agent->screeningPrompt());
        } catch (Throwable $exception) {
            Log::error("Sign-up screening failed for user {$user->id}: {$exception->getMessage()}");

            return;
        }

        $this->aiUsageService->record($user->id, AiUsage::FEATURE_SIGNUP_SCREENING, $response, $user);

        if (! ($response['flag'] ?? false)) {
            return;
        }

        $user
            ->forceFill(['flag' => [
                'at' => now(),
                'reason' => Str::limit((string) ($response['reason'] ?? 'Flagged by sign-up screening'), 250),
                'source' => User::FLAG_SOURCE_SIGNUP_SCREENING,
                'refuted_at' => null,
            ]])
            ->save();

        UserFlaggedEvent::dispatch($user);
    }

    /**
     * Existing accounts that may be the same person: the same name, the
     * same email stem ("j.kamau12" and "jkamau" share "jkamau"), or a login
     * from the same IP address. Each carries shares_ip for the agent.
     *
     * @return Collection<int, User>
     */
    public function lookAlikes(User $user, ?string $ipAddress): Collection
    {
        $stem = $this->emailStem($user->email);

        return User::query()
            ->whereKeyNot($user->id)
            ->where('is_seed', false)
            ->where(function (Builder $query) use ($user, $stem, $ipAddress) {
                $query
                    ->where('name', $user->getRawOriginal('name'))
                    ->when(
                        Str::length($stem) >= self::MIN_STEM_LENGTH,
                        fn (Builder $query) => $query->orWhereRaw("REPLACE(SUBSTRING_INDEX(email, '@', 1), '.', '') LIKE ?", [$stem.'%'])
                    )
                    ->when(
                        $ipAddress,
                        function (Builder $query) use ($ipAddress) {
                            $query->orWhereHas('logins', fn (Builder $query) => $query->where('ip_address', $ipAddress));
                        }
                    );
            })
            ->withExists(['logins as shares_ip' => fn (Builder $query) => $query->where('ip_address', $ipAddress)])
            ->latest()
            ->limit(config('services.signup_screening.max_candidates'))
            ->get();
    }

    /**
     * The email's local part without dots, a "+tag" or trailing digits,
     * lowercased: "J.Kamau+gallery12@gmail.com" becomes "jkamau".
     */
    private function emailStem(string $email): string
    {
        return Str::of($email)
            ->before('@')
            ->before('+')
            ->remove('.')
            ->lower()
            ->replaceMatches('/\d+$/', '')
            ->toString();
    }
}
