<?php

namespace App\Http\Services;

use App\Ai\Agents\SupportAgent;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketStatus;
use App\Events\SupportTicketEscalatedEvent;
use App\Events\SupportTicketMessageCreatedEvent;
use App\Models\AiUsage;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\TemporaryUpload;
use App\Models\User;
use App\Services\AiUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SupportTicketService extends Service
{
    /**
     * Shown to the customer when the assistant can't produce a reply (the
     * AI provider is down, or the ticket hit its reply limit).
     */
    public const HANDOFF_MESSAGE = "I've passed your question to our support team. Someone will reply here as soon as they can.";

    public function __construct(protected AiUsageService $aiUsageService)
    {
        parent::__construct();
    }

    /**
     * The signed-in user's own tickets, most recent first.
     */
    public function index(User $user)
    {
        return SupportTicket::with('latestMessage')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(20);
    }

    /**
     * One of the user's own tickets, with the whole conversation. Anyone
     * else's ticket is a 404, not a 403, so ids can't be probed.
     */
    public function findForUser(User $user, string $id): SupportTicket
    {
        return SupportTicket::with(['user', 'messages'])
            ->where('user_id', $user->id)
            ->findOrFail($id);
    }

    /**
     * Open a ticket with the customer's first message. The subject comes
     * from the message unless one was given.
     */
    public function store(Request $request, User $user): SupportTicket
    {
        $body = trim($request->input('message'));

        $supportTicket = DB::transaction(function () use ($request, $user, $body) {
            $supportTicket = new SupportTicket;
            $supportTicket->user_id = $user->id;
            $supportTicket->category = $request->input('category', SupportTicketCategory::OTHER->value);
            $supportTicket->subject = $request->input('subject') ?: Str::limit(Str::squish($body), 80);
            $supportTicket->priority = $request->input('priority');
            $supportTicket->description = $body;
            $supportTicket->status = SupportTicketStatus::OPEN->value;
            $supportTicket->attachments = $this->moveAttachments($request) ?: null;
            $supportTicket->save();

            return $supportTicket;
        });

        $this->addMessage($supportTicket, SupportTicketMessage::AUTHOR_USER, $body, $user);

        return $supportTicket->load(['user', 'messages']);
    }

    /**
     * Add a message to the conversation. A customer writing on a finished
     * ticket reopens it, so the assistant picks it up again.
     */
    public function addMessage(SupportTicket $supportTicket, string $author, string $body, ?User $user = null): SupportTicketMessage
    {
        $finished = [SupportTicketStatus::RESOLVED->value, SupportTicketStatus::CLOSED->value];

        if ($author === SupportTicketMessage::AUTHOR_USER && in_array($supportTicket->status, $finished, true)) {
            $supportTicket->update(['status' => SupportTicketStatus::OPEN->value]);
        }

        if ($author === SupportTicketMessage::AUTHOR_AGENT && $supportTicket->status !== SupportTicketStatus::RESOLVED->value) {
            $supportTicket->update(['status' => SupportTicketStatus::IN_PROGRESS->value]);
        }

        $message = $supportTicket->messages()->create([
            'user_id' => $user?->id,
            'author' => $author,
            'body' => trim($body),
        ]);

        // Bumps the ticket up the admin inbox.
        $supportTicket->touch();

        SupportTicketMessageCreatedEvent::dispatch($message);

        return $message;
    }

    /**
     * Hand the ticket to a person. The note, if given, is posted as the
     * assistant so the customer knows what happens next.
     */
    public function escalate(SupportTicket $supportTicket, ?string $note = null): void
    {
        if ($supportTicket->status !== SupportTicketStatus::OPEN->value) {
            return;
        }

        $supportTicket->update(['status' => SupportTicketStatus::NEEDS_AGENT->value]);

        if ($note) {
            $this->addMessage($supportTicket, SupportTicketMessage::AUTHOR_ASSISTANT, $note);
        }

        SupportTicketEscalatedEvent::dispatch($supportTicket);
    }

    /**
     * Reply to the customer's latest message(s) with the AI assistant, or
     * hand the ticket to a person when the assistant can't help.
     */
    public function answerWithAssistant(SupportTicket $supportTicket): void
    {
        $supportTicket->refresh();

        if ($supportTicket->status !== SupportTicketStatus::OPEN->value) {
            return;
        }

        // Already answered, e.g. by the job for an earlier message that
        // ran after this one arrived.
        if ($supportTicket->latestMessage?->author !== SupportTicketMessage::AUTHOR_USER) {
            return;
        }

        $replies = $supportTicket->messages()
            ->where('author', SupportTicketMessage::AUTHOR_ASSISTANT)
            ->count();

        if ($replies >= config('services.support_assistant.max_replies')) {
            $this->escalate($supportTicket, self::HANDOFF_MESSAGE);

            return;
        }

        try {
            $agent = new SupportAgent($supportTicket);
            $response = $agent->prompt($agent->pendingQuestion());
        } catch (Throwable $exception) {
            Log::error("Support assistant failed on ticket {$supportTicket->id}: {$exception->getMessage()}");

            $this->escalate($supportTicket, self::HANDOFF_MESSAGE);

            return;
        }

        $this->aiUsageService->record($supportTicket->user_id, AiUsage::FEATURE_SUPPORT_REPLY, $response, $supportTicket);

        $reply = trim((string) ($response['reply'] ?? ''));

        if ($reply === '') {
            $this->escalate($supportTicket, self::HANDOFF_MESSAGE);

            return;
        }

        $this->addMessage($supportTicket, SupportTicketMessage::AUTHOR_ASSISTANT, $reply);

        if ($response['escalate'] ?? false) {
            $this->escalate($supportTicket);
        }
    }

    public function destroy(SupportTicket $supportTicket): bool
    {
        return DB::transaction(function () use ($supportTicket) {
            foreach ($supportTicket->attachments ?? [] as $attachment) {
                Storage::disk('public')->delete($attachment);
            }

            return $supportTicket->delete();
        });
    }

    /**
     * Search / filter parameters for the admin inbox.
     */
    public function search($query, Request $request)
    {
        if ($request->filled('number')) {
            $query->where('number', 'LIKE', "%{$request->number}%");
        }

        if ($request->filled('subject')) {
            $query->where('subject', 'LIKE', "%{$request->subject}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $query;
    }

    /**
     * Move FilePond's temporary uploads into the support ticket folder.
     *
     * @return array<int, string>
     */
    private function moveAttachments(Request $request): array
    {
        $temporaryUploadIds = collect($request->input('temporaryUploadIds', []))
            ->filter()
            ->unique()
            ->values();

        if ($temporaryUploadIds->isEmpty()) {
            return [];
        }

        $temporaryUploads = TemporaryUpload::query()
            ->ownedBy($request->user())
            ->whereIn('id', $temporaryUploadIds)
            ->get();

        $paths = [];

        foreach ($temporaryUploads as $temporaryUpload) {
            $disk = $temporaryUpload->disk ?: 'public';
            $finalPath = 'support-tickets/attachments/'.basename($temporaryUpload->path);

            Storage::disk($disk)->move($temporaryUpload->path, $finalPath);
            $paths[] = $finalPath;
        }

        TemporaryUpload::whereIn('id', $temporaryUploads->pluck('id'))->delete();

        return $paths;
    }
}
