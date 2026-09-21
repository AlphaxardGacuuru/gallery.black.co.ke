<?php

namespace Tests\Feature;

use App\Enums\EmailNotificationCategory;
use App\Models\PhotoCompetition;
use App\Models\User;
use App\Notifications\PhotoCompetitionStartedNotification;
use App\Notifications\PhotoCompetitionWonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_defaults_both_competition_email_preferences_to_enabled(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk();

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue($user->wantsEmail(EmailNotificationCategory::COMPETITION_STARTED));
        $this->assertTrue($user->wantsEmail(EmailNotificationCategory::COMPETITION_WON));
    }

    public function test_notification_skips_mail_channel_once_the_user_opts_out(): void
    {
        $user = User::factory()->create([
            'settings' => ['competitionStartedNotification' => false],
        ]);
        $competition = PhotoCompetition::factory()->create();

        $channels = (new PhotoCompetitionStartedNotification($competition))->via($user);

        $this->assertNotContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_notification_includes_mail_channel_by_default(): void
    {
        $user = User::factory()->create(['settings' => []]);
        $competition = PhotoCompetition::factory()->create();

        $startedChannels = (new PhotoCompetitionStartedNotification($competition))->via($user);
        $wonChannels = (new PhotoCompetitionWonNotification($competition))->via($user);

        $this->assertContains('mail', $startedChannels);
        $this->assertContains('mail', $wonChannels);
    }

    public function test_unsubscribe_link_turns_off_only_the_linked_category(): void
    {
        $user = User::factory()->create([
            'settings' => [
                'competitionStartedNotification' => true,
                'competitionWonNotification' => true,
            ],
        ]);

        $url = $user->unsubscribeUrlFor(EmailNotificationCategory::COMPETITION_STARTED);

        $this->get($url)->assertOk();

        $user->refresh();
        $this->assertFalse($user->wantsEmail(EmailNotificationCategory::COMPETITION_STARTED));
        $this->assertTrue($user->wantsEmail(EmailNotificationCategory::COMPETITION_WON));
    }

    public function test_unsubscribe_link_rejects_a_tampered_signature(): void
    {
        $user = User::factory()->create();

        $url = URL::signedRoute('unsubscribe.show', [
            'user' => $user->id,
            'category' => EmailNotificationCategory::COMPETITION_STARTED->value,
        ]);

        $this->get($url . '&signature=tampered')->assertForbidden();

        $this->assertTrue($user->fresh()->wantsEmail(EmailNotificationCategory::COMPETITION_STARTED));
    }

    public function test_unsubscribe_link_rejects_an_unknown_category(): void
    {
        $user = User::factory()->create();

        $url = URL::signedRoute('unsubscribe.show', [
            'user' => $user->id,
            'category' => 'not-a-real-category',
        ]);

        $this->get($url)->assertNotFound();
    }

    public function test_user_can_update_their_email_notification_preferences(): void
    {
        $user = User::factory()->create([
            'settings' => [
                'installOnboardedAt' => '2026-01-01T00:00:00Z',
                'competitionStartedNotification' => true,
                'competitionWonNotification' => true,
            ],
        ]);

        $this->actingAs($user)
            ->patchJson('/settings/notifications', [
                'competitionStartedNotification' => false,
                'competitionWonNotification' => true,
            ])
            ->assertOk();

        $user->refresh();
        $this->assertFalse($user->wantsEmail(EmailNotificationCategory::COMPETITION_STARTED));
        $this->assertTrue($user->wantsEmail(EmailNotificationCategory::COMPETITION_WON));
        // Unrelated settings keys survive the update.
        $this->assertSame('2026-01-01T00:00:00Z', $user->settings->installOnboardedAt);
    }
}
