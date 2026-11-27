<?php

namespace App\Jobs;

use App\Models\UserEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes one user_events row off the request, so recording never slows the
 * action down. The attributes carry their own created_at, so a busy queue
 * doesn't shift when the event happened.
 */
class RecordUserEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $attributes) {}

    public function handle(): void
    {
        UserEvent::query()
            ->create($this->attributes);
    }
}
