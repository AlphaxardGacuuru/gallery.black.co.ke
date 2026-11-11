<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\AdminNewUserSignupNotification;
use App\Notifications\AdminSupportTicketMessageNotification;
use App\Notifications\PhotoLikedNotification;
use App\Notifications\SupportTicketRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PushActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    /**
     * The web push payload a notification would send $notifiable.
     *
     * @return array<string, mixed>
     */
    private function push(object $notification, User $notifiable): array
    {
        return $notification
            ->toWebPush($notifiable, $notification)
            ->toArray();
    }

    private function ticket(User $customer): SupportTicket
    {
        return SupportTicket::forceCreate([
            'number' => random_int(1000, 9999),
            'user_id' => $customer->id,
            'category' => 'general',
            'subject' => 'Help',
            'status' => 'in_progress',
        ]);
    }

    public function test_like_back_likes_the_likers_photo_through_the_signed_link(): void
    {
        $competition = PhotoCompetition::factory()->create();
        $owner = User::factory()->create();
        $liker = User::factory()->create();
        $photo = Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $owner->id]);
        $likersPhoto = Photo::factory()->create(['competition_id' => $competition->id, 'user_id' => $liker->id]);

        $push = $this->push(new PhotoLikedNotification($photo, $liker), $owner);

        $this->assertSame(['like-back', 'view'], array_column($push['actions'], 'action'));
        $this->assertSame("/photos/{$photo->id}/show", $push['data']['actions']['view']['url']);

        $this->postJson($push['data']['actions']['like-back']['post'])
            ->assertOk();

        $this->assertDatabaseHas('photo_likes', ['photo_id' => $likersPhoto->id, 'user_id' => $owner->id]);
        $this->assertSame(1, $likersPhoto->fresh()->likes_count);
    }

    public function test_there_is_no_like_back_when_the_liker_has_no_photo_in_the_challenge(): void
    {
        $photo = Photo::factory()->create(['competition_id' => PhotoCompetition::factory()->create()->id]);

        $push = $this->push(new PhotoLikedNotification($photo, User::factory()->create()), $photo->user);

        $this->assertSame(['view'], array_column($push['actions'], 'action'));
    }

    public function test_a_push_action_needs_a_valid_unexpired_signature(): void
    {
        $owner = User::factory()->create();
        $photo = Photo::factory()->create(['competition_id' => PhotoCompetition::factory()->create()->id]);

        $this->postJson("/api/push-actions/{$owner->id}/photos/{$photo->id}/like")
            ->assertForbidden();

        $expired = URL::temporarySignedRoute(
            'push-actions.photos.like',
            now()->subMinute(),
            ['user' => $owner->id, 'photo' => $photo->id],
            absolute: false
        );

        $this->postJson($expired)
            ->assertForbidden();

        $this->assertSame(0, $photo->likes()->count());
    }

    public function test_mark_as_read_clears_the_customers_unread_reply(): void
    {
        $customer = User::factory()->create();
        $ticket = $this->ticket($customer);
        $message = $ticket->messages()->create(['author' => SupportTicketMessage::AUTHOR_AGENT, 'body' => 'Sorted.']);

        $push = $this->push(new SupportTicketRepliedNotification($message), $customer);

        $this->postJson($push['data']['actions']['mark-read']['post'])
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->user_read_at);
    }

    public function test_a_signed_link_only_works_for_the_user_it_was_sent_to(): void
    {
        $ticket = $this->ticket(User::factory()->create());

        $link = URL::temporarySignedRoute(
            'push-actions.support-tickets.read',
            now()->addDay(),
            ['user' => User::factory()->create()->id, 'supportTicket' => $ticket->id],
            absolute: false
        );

        $this->postJson($link)
            ->assertForbidden();
    }

    public function test_the_admin_can_resolve_a_ticket_and_verify_a_signup_from_a_push(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $ticket = $this->ticket(User::factory()->create());
        $message = $ticket->messages()->create(['author' => SupportTicketMessage::AUTHOR_USER, 'body' => 'Any news?']);
        $newUser = User::factory()->create(['verified' => false]);

        $resolve = $this->push(new AdminSupportTicketMessageNotification($message), $admin);
        $verify = $this->push(new AdminNewUserSignupNotification($newUser, null), $admin);

        $this->postJson($resolve['data']['actions']['resolve']['post'])
            ->assertOk();
        $this->postJson($verify['data']['actions']['verify']['post'])
            ->assertOk();

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertTrue((bool) $newUser->fresh()->verified);
    }

    public function test_admin_actions_are_refused_for_anyone_else(): void
    {
        $customer = User::factory()->create();
        $newUser = User::factory()->create(['verified' => false]);

        $verify = $this->push(new AdminNewUserSignupNotification($newUser, null), $customer);

        $this->postJson($verify['data']['actions']['verify']['post'])
            ->assertForbidden();

        $this->assertFalse((bool) $newUser->fresh()->verified);
    }

    public function test_every_web_push_notification_has_a_button(): void
    {
        collect(glob(app_path('Notifications/*.php')))
            ->map(fn (string $path) => file_get_contents($path))
            ->filter(fn (string $source) => str_contains($source, 'function toWebPush'))
            ->each(fn (string $source) => $this->assertMatchesRegularExpression('/->(openAction|signedAction)\(/', $source));
    }
}
