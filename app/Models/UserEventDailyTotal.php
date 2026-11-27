<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How many times an event happened on one day, and by how many people.
 * Filled by UserEventSummaryService.
 */
class UserEventDailyTotal extends Model
{
    /** The event name on each day's row counting everyone active that day. */
    public const ALL_EVENTS = '*';

    protected $fillable = [
        'date',
        'event',
        'source',
        'route',
        'events',
        'users',
        'guests',
    ];

    protected $casts = [
        'date' => 'date',
        'events' => 'integer',
        'users' => 'integer',
        'guests' => 'integer',
    ];
}
