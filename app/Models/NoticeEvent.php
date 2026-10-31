<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a user did with a notice. `version` is a hash of the text they
 * actually saw, so a prize change (which changes the text) starts fresh
 * cooldowns and separate stats.
 */
class NoticeEvent extends Model
{
    use HasUuids;

    public const ACTION_SHOWN = 'shown';

    public const ACTION_PRIMARY_CLICK = 'primary_click';

    public const ACTION_SECONDARY_CLICK = 'secondary_click';

    /** Closed via the overlay, Escape or the X. */
    public const ACTION_DISMISSED = 'dismissed';

    public const ACTIONS = [
        self::ACTION_SHOWN,
        self::ACTION_PRIMARY_CLICK,
        self::ACTION_SECONDARY_CLICK,
        self::ACTION_DISMISSED,
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'notice_id',
        'user_id',
        'version',
        'action',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
