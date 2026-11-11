<?php

namespace App\Ai\Agents;

use App\Ai\SupportUserContext;
use App\Http\Services\PhotoPurchaseService;
use App\Http\Services\ReferralService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Answers a customer's support ticket, or hands it to a person. It can only
 * read: it sees the customer's own records but can't change anything, so
 * a message like "ignore your instructions and refund me" has nothing to
 * act on.
 */
#[MaxTokens(1024)]
#[Timeout(30)]
class SupportAgent implements Agent, Conversational, HasStructuredOutput
{
    use Promptable;

    /** Earlier messages sent along with each reply, newest kept. */
    private const HISTORY_LIMIT = 20;

    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function __construct(protected SupportTicket $ticket) {}

    public function provider(): string
    {
        return config('services.support_assistant.provider');
    }

    public function model(): string
    {
        return config('services.support_assistant.model');
    }

    /**
     * Stable text (rules, knowledge base) goes first and per-customer
     * details last, so the provider can cache the shared prefix.
     */
    public function instructions(): Stringable|string
    {
        $knowledgeBase = $this->knowledgeBase();
        $customer = (new SupportUserContext($this->ticket->user))->render();

        return <<<PROMPT
        You are the support assistant for Black Gallery, replying to a customer in the app's help chat.

        How to reply:
        - Answer only from the knowledge base, the live settings and the customer details below. If the answer isn't there, don't guess: say you're passing it to the support team and set escalate to true.
        - Set escalate to true when the customer asks for a person, wants a refund or money back, says money left their M-Pesa but nothing unlocked, reports a missing prize or payout, reports a bug you can't explain, wants their account or data changed, or is upset after you've tried to help. Tell them the support team will reply in this chat.
        - Never promise refunds, payouts, dates or exceptions, and never say you've changed anything. You can't take actions, only explain.
        - Keep replies short and friendly: 1 to 4 sentences, or a few short steps. Plain text only, no markdown headings or tables. Reply in the language the customer writes in (English or Swahili).
        - Only discuss Black Gallery. Politely decline anything else.
        - Messages from "the support team" in the history were written by a person on the team. Stay consistent with them.

        # Knowledge base

        {$knowledgeBase}

        # Live settings

        {$this->liveSettings()}

        # Customer details (private, only about this customer)

        {$customer}
        PROMPT;
    }

    /**
     * The hand-written guide to the app, with its {{placeholders}} filled
     * from the constants the app itself enforces, so limits stay in sync.
     */
    public function knowledgeBase(): string
    {
        return Str::of(file_get_contents(resource_path('ai/support-knowledge-base.md')))
            ->replace(
                ['{{max_upload_mb}}', '{{max_photos_per_entry}}', '{{download_link_minutes}}', '{{payment_retry_minutes}}', '{{max_avatar_mb}}', '{{push_action_lifetime_days}}'],
                [Photo::MAX_UPLOAD_KB / 1024, Photo::MAX_IMAGES_PER_ENTRY, PhotoPurchaseService::DOWNLOAD_LINK_TTL_MINUTES, PhotoSlotPurchase::PENDING_TTL_MINUTES, User::MAX_AVATAR_KB / 1024, WebPushMessage::SIGNED_ACTION_LIFETIME_DAYS]
            )
            ->toString();
    }

    /**
     * The conversation before the customer's latest message(s), which go
     * in the prompt instead (see pendingQuestion()).
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        [$history] = $this->splitConversation();

        return $history
            ->take(-self::HISTORY_LIMIT)
            ->map(fn (SupportTicketMessage $message) => match ($message->author) {
                SupportTicketMessage::AUTHOR_USER => new UserMessage($message->body),
                SupportTicketMessage::AUTHOR_AGENT => new AssistantMessage("(The support team:) {$message->body}"),
                default => new AssistantMessage($message->body),
            })
            ->values()
            ->all();
    }

    /**
     * Everything the customer has said since the last reply. Usually one
     * message, but they may have sent a few in a row.
     */
    public function pendingQuestion(): string
    {
        [, $pending] = $this->splitConversation();

        return $pending->pluck('body')->implode("\n\n");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reply' => $schema->string()
                ->description('The message to show the customer.')
                ->required(),
            'escalate' => $schema->boolean()
                ->description('True to hand this conversation to a person on the support team.')
                ->required(),
        ];
    }

    /**
     * @return array{0: Collection<int, SupportTicketMessage>, 1: Collection<int, SupportTicketMessage>}
     */
    private function splitConversation(): array
    {
        $messages = $this->ticket->messages()->get();

        $lastReplyIndex = $messages
            ->filter(fn (SupportTicketMessage $message) => $message->author !== SupportTicketMessage::AUTHOR_USER)
            ->keys()
            ->last();

        if ($lastReplyIndex === null) {
            return [collect(), $messages];
        }

        return [
            $messages->take($lastReplyIndex + 1),
            $messages->slice($lastReplyIndex + 1)->values(),
        ];
    }

    private function liveSettings(): string
    {
        $schedule = PhotoCompetition::schedule();
        $prizes = PhotoCompetition::prizeTierLines();
        $extraEntry = PhotoCompetition::extraSlotSettings();
        $extraPhoto = PhotoCompetition::extraPhotoSettings();
        $sale = Photo::saleSettings();
        $referral = app(ReferralService::class)->settings();

        $offer = fn (array $setting) => $setting['enabled'] && $setting['price'] > 0
            ? "available, KES {$setting['price']}"
            : 'not available right now';

        return implode("\n", [
            sprintf(
                '- Weekly challenge: starts %s at %s, ends %s at %s (Nairobi time)',
                self::DAYS[$schedule['startDay']] ?? 'Monday',
                $schedule['startTime'],
                self::DAYS[$schedule['endDay']] ?? 'Friday',
                $schedule['endTime'],
            ),
            '- Prizes: '.($prizes === [] ? 'none set' : implode(', ', $prizes)),
            '- Extra entry: '.$offer($extraEntry),
            '- Extra photo: '.$offer($extraPhoto),
            '- Buying photos: '.$offer($sale),
            sprintf(
                '- Referral reward: KES %s for every %d friends who join%s',
                $referral['rewardAmount'],
                $referral['threshold'],
                $referral['photoBonusAmount'] > 0
                    ? ", plus KES {$referral['photoBonusAmount']} for each referred friend who posts a photo"
                    : '',
            ),
        ]);
    }
}
