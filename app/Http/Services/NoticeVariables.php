<?php

namespace App\Http\Services;

use App\Models\PhotoCompetition;
use App\Models\User;
use Closure;
use Illuminate\Support\Str;

/**
 * The {variables} a notice's text may use. Only names listed in
 * resolvers() exist, so notice text can't reach anything else.
 */
class NoticeVariables
{
    public function __construct(protected ReferralService $referralService)
    {
        //
    }

    /**
     * @return array<string, array{description: string, resolve: Closure(User): (string|int|float|null)}>
     */
    public function resolvers(): array
    {
        return [
            'rewardAmount' => [
                'description' => 'Referral reward (KES)',
                'resolve' => fn() => $this->referralService->settings()['rewardAmount'],
            ],
            'threshold' => [
                'description' => 'Referrals needed per reward',
                'resolve' => fn() => $this->referralService->settings()['threshold'],
            ],
            'photoBonusAmount' => [
                'description' => 'Bonus per referred friend who posts (KES)',
                'resolve' => fn() => $this->referralService->settings()['photoBonusAmount'],
            ],
            'topPrize' => [
                'description' => 'First place prize (KES)',
                'resolve' => fn() => PhotoCompetition::prizeTiers()[0] ?? null,
            ],
            'firstName' => [
                'description' => "The viewer's first name",
                'resolve' => fn(User $user) => Str::before(trim((string) $user->name), ' ') ?: null,
            ],
        ];
    }

    /**
     * Fill in every {variable} in $text. Returns null if any of them is
     * unknown or has no value, so the caller can skip the notice instead of
     * showing a half-filled sentence.
     */
    public function render(string $text, User $user): ?string
    {
        preg_match_all('/\{(\w+)\}/', $text, $matches);
        $resolvers = $this->resolvers();
        $values = [];

        foreach (array_unique($matches[1]) as $name) {
            $value = isset($resolvers[$name]) ? $resolvers[$name]['resolve']($user) : null;

            if ($value === null || $value === '') {
                return null;
            }

            $values['{' . $name . '}'] = $this->format($value);
        }

        return strtr($text, $values);
    }

    /**
     * Variable names in $text that resolvers() doesn't know, for
     * validating notices as they're saved.
     *
     * @return list<string>
     */
    public function unknownIn(string $text): array
    {
        preg_match_all('/\{(\w+)\}/', $text, $matches);

        return array_values(array_diff(array_unique($matches[1]), array_keys($this->resolvers())));
    }

    /**
     * Each variable's description and its value right now, for the admin
     * editor. $user stands in for the viewer in per-user variables.
     *
     * @return list<array{name: string, description: string, example: string|null}>
     */
    public function catalogue(User $user): array
    {
        return collect($this->resolvers())
            ->map(function (array $variable, string $name) use ($user) {
                $value = $variable['resolve']($user);

                return [
                    'name' => $name,
                    'description' => $variable['description'],
                    'example' => $value === null ? null : $this->format($value),
                ];
            })
            ->values()
            ->all();
    }

    private function format(string|int|float $value): string
    {
        if (is_numeric($value)) {
            $number = (float) $value;

            return number_format($number, floor($number) == $number ? 0 : 2);
        }

        return $value;
    }
}
