<?php

namespace App\Models;

use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The tokens and cost of one AI request, so spend can be totalled per user.
 * Costs are stored in micro-dollars (millionths of a US dollar) because a single
 * request usually costs a small fraction of a cent.
 */
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory, HasUuids;

    /**
     * The support assistant replying to a customer's support ticket.
     */
    public const FEATURE_SUPPORT_REPLY = 'support_reply';

    /**
     * Setting that switches every AI agent on or off, see agentsEnabled().
     */
    public const SETTING_AGENTS_ENABLED = 'ai_agents_enabled';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'feature',
        'provider',
        'model',
        'attempt',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'reasoning_tokens',
        'cost_micros',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'input_tokens' => 'integer',
            'cached_input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'cost_micros' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether AI agents may run. On until an admin switches them off on
     * the AI usage page.
     */
    public static function agentsEnabled(): bool
    {
        return (bool) (Setting::query()
            ->where('key', self::SETTING_AGENTS_ENABLED)
            ->value('value') ?? true);
    }
}
