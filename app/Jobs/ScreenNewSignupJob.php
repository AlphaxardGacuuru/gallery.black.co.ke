<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SignupScreeningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Screens a new sign-up off the request, so signing in never waits on the
 * AI. The IP address is passed in because the queue has no request.
 */
class ScreenNewSignupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $ipAddress,
    ) {}

    public function handle(SignupScreeningService $service): void
    {
        $service->screen($this->user->fresh(), $this->ipAddress);
    }
}
