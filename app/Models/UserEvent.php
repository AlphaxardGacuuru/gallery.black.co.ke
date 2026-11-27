<?php

namespace App\Models;

use App\Enums\UserEventName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a user did, recorded by EventRecorder for usage analytics.
 * Page views are most of the rows, so raw ones are kept for
 * PAGE_VIEW_RETENTION_DAYS; their daily totals stay for good. Every other
 * event is kept.
 */
class UserEvent extends Model
{
    use HasUuids, Prunable;

    public const UPDATED_AT = null;

    public const PAGE_VIEW_RETENTION_DAYS = 90;

    protected $fillable = [
        'user_id',
        'anonymous_id',
        'event',
        'photo_id',
        'competition_id',
        'source',
        'path',
        'properties',
        'created_at',
    ];

    protected $casts = [
        'event' => UserEventName::class,
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::query()
            ->where('event', UserEventName::PAGE_VIEWED->value)
            ->where('created_at', '<', now()->subDays(self::PAGE_VIEW_RETENTION_DAYS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
