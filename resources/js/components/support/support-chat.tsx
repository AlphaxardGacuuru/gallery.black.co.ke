import { ArrowLeft, CheckCircle2, MessageSquarePlus, UserRound } from "lucide-react"
import { useQueryClient } from "@tanstack/react-query"
import { useEffect, useRef, useState } from "react"
import {
	SupportComposer,
	SupportMessageBubble,
	SupportStatusBadge,
	TypingIndicator,
	formatSupportTime,
} from "@/components/support/support-message"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import toast from "@/lib/toast"
import {
	type SupportTicket,
	isAwaitingReply,
	useCloseSupportTicket,
	useEscalateSupportTicket,
	useSendSupportMessage,
	useStartSupportTicket,
	useSupportTicket,
	useSupportTickets,
} from "@/queries/support"

const SUGGESTIONS = [
	"How do I enter this week's challenge?",
	"I paid but nothing unlocked",
	"When will I get my prize?",
	"How do referrals work?",
]

type View = { name: "list" } | { name: "new" } | { name: "thread"; id: string }

function errorMessage(error: unknown): string {
	const response = (error as { response?: { status?: number; data?: { message?: string } } })
		?.response

	if (response?.status === 429) {
		return "You're sending messages too quickly. Wait a minute and try again."
	}

	return response?.data?.message ?? "Couldn't send your message. Try again."
}

/**
 * The customer's help chat: their past conversations, starting a new one,
 * and a conversation itself. The AI assistant answers first and hands over
 * to the support team when it can't help.
 */
export function SupportChat({ initialTicketId = null }: { initialTicketId?: string | null }) {
	const { data: tickets, isLoading } = useSupportTickets()
	const [view, setView] = useState<View>(
		initialTicketId ? { name: "thread", id: initialTicketId } : { name: "list" }
	)

	// First-timers have nothing to list, so go straight to asking.
	useEffect(() => {
		if (view.name === "list" && tickets && tickets.length === 0) {
			setView({ name: "new" })
		}
	}, [tickets, view.name])

	if (view.name === "thread") {
		return (
			<SupportThread
				id={view.id}
				onBack={() => setView({ name: "list" })}
			/>
		)
	}

	if (view.name === "new") {
		return (
			<NewConversation
				onStarted={(ticket) => setView({ name: "thread", id: ticket.id })}
				onBack={tickets && tickets.length > 0 ? () => setView({ name: "list" }) : undefined}
			/>
		)
	}

	return (
		<div className="flex h-full flex-col">
			<div className="space-y-3 p-4">
				<Button
					className="w-full"
					size="lg"
					onClick={() => setView({ name: "new" })}>
					<MessageSquarePlus />
					Start a new conversation
				</Button>
			</div>
			<div className="flex-1 space-y-2 overflow-y-auto px-4 pb-4">
				{isLoading &&
					Array.from({ length: 3 }).map((_, index) => (
						<Skeleton
							key={index}
							className="h-16 rounded-xl"
						/>
					))}
				{tickets?.map((ticket) => (
					<button
						key={ticket.id}
						type="button"
						onClick={() => setView({ name: "thread", id: ticket.id })}
						className="w-full rounded-xl border p-3 text-left transition-colors hover:bg-muted/60">
						<div className="flex items-start justify-between gap-2">
							<p className="flex min-w-0 items-center gap-1.5 text-sm font-medium">
								{ticket.unread && (
									<span
										className="size-2 shrink-0 rounded-full bg-destructive"
										aria-label="Unread reply"
									/>
								)}
								<span className="line-clamp-1">{ticket.subject}</span>
							</p>
							<SupportStatusBadge status={ticket.status} />
						</div>
						{ticket.latestMessage && (
							<p className="mt-1 line-clamp-1 text-xs text-muted-foreground">
								{ticket.latestMessage.body}
							</p>
						)}
						<p className="mt-1 text-[11px] text-muted-foreground">
							{ticket.number} · {formatSupportTime(ticket.lastActivityAt)}
						</p>
					</button>
				))}
			</div>
		</div>
	)
}

function Greeting() {
	const { auth } = useApp()
	const firstName = auth?.name?.split(" ")[0]

	return (
		<div className="flex items-start">
			<div className="max-w-[85%] rounded-2xl rounded-bl-sm bg-muted px-3.5 py-2 text-sm">
				Hi{firstName ? ` ${firstName}` : ""}! I'm the Black Gallery assistant. Ask me
				anything about the photo challenge, payments or your account. If I can't
				help, I'll pass you to someone on our team.
			</div>
		</div>
	)
}

function NewConversation({
	onStarted,
	onBack,
}: {
	onStarted: (ticket: SupportTicket) => void
	onBack?: () => void
}) {
	const start = useStartSupportTicket()

	async function send(message: string) {
		try {
			onStarted(await start.mutateAsync(message))
		} catch (error) {
			toast.error(errorMessage(error))
			throw error
		}
	}

	return (
		<div className="flex h-full flex-col">
			{onBack && (
				<div className="border-b px-2 py-1.5">
					<Button
						variant="ghost"
						size="sm"
						onClick={onBack}>
						<ArrowLeft />
						Conversations
					</Button>
				</div>
			)}
			<div className="flex-1 space-y-4 overflow-y-auto p-4">
				<Greeting />
				{start.isPending ? (
					<>
						<SupportMessageBubble
							mine
							message={{
								id: "pending",
								author: "user",
								body: start.variables ?? "",
								createdAt: new Date().toISOString(),
							}}
						/>
						<TypingIndicator />
					</>
				) : (
					<div className="flex flex-wrap gap-2 pt-2">
						{SUGGESTIONS.map((suggestion) => (
							<button
								key={suggestion}
								type="button"
								onClick={() => send(suggestion).catch(() => {})}
								className="rounded-full border px-3 py-1.5 text-xs transition-colors hover:bg-muted">
								{suggestion}
							</button>
						))}
					</div>
				)}
			</div>
			<SupportComposer
				onSend={send}
				sending={start.isPending}
				placeholder="Ask a question…"
				autoFocus
			/>
		</div>
	)
}

function SupportThread({ id, onBack }: { id: string; onBack: () => void }) {
	const { data: ticket, isLoading, isError } = useSupportTicket(id)
	const sendMessage = useSendSupportMessage(id)
	const escalate = useEscalateSupportTicket(id)
	const close = useCloseSupportTicket(id)
	const bottomRef = useRef<HTMLDivElement>(null)
	const queryClient = useQueryClient()

	const messages = ticket?.messages ?? []
	const awaitingReply = isAwaitingReply(ticket) || sendMessage.isPending
	const hasAssistantReply = messages.some((message) => message.author === "assistant")

	useEffect(() => {
		bottomRef.current?.scrollIntoView({ block: "end" })
	}, [messages.length, awaitingReply])

	// Loading the conversation marks it read on the server, so refresh the
	// Feedback badge and the list's unread dots once each new reply is on
	// screen.
	useEffect(() => {
		if (!ticket) {
			return
		}

		queryClient.invalidateQueries({ queryKey: ["support-pending"] })
		queryClient.invalidateQueries({ queryKey: ["support-tickets"], exact: true })
	}, [ticket?.id, messages.length, queryClient])

	async function send(message: string) {
		try {
			await sendMessage.mutateAsync(message)
		} catch (error) {
			toast.error(errorMessage(error))
			throw error
		}
	}

	return (
		<div className="flex h-full flex-col">
			<div className="flex items-center gap-2 border-b px-2 py-1.5">
				<Button
					variant="ghost"
					size="sm"
					onClick={onBack}>
					<ArrowLeft />
					Conversations
				</Button>
				{ticket && (
					<div className="ml-auto pr-2">
						<SupportStatusBadge status={ticket.status} />
					</div>
				)}
			</div>

			<div className="flex-1 space-y-4 overflow-y-auto p-4">
				{isLoading && <Skeleton className="h-24 rounded-xl" />}
				{isError && (
					<p className="text-sm text-muted-foreground">
						We couldn't load this conversation.
					</p>
				)}
				{ticket && <Greeting />}
				{messages.map((message) => (
					<SupportMessageBubble
						key={message.id}
						message={message}
						mine={message.author === "user"}
					/>
				))}
				{sendMessage.isPending && (
					<SupportMessageBubble
						mine
						message={{
							id: "pending",
							author: "user",
							body: sendMessage.variables ?? "",
							createdAt: new Date().toISOString(),
						}}
					/>
				)}
				{awaitingReply && ticket?.status === "open" && <TypingIndicator />}

				{ticket?.status === "needs_agent" && (
					<p className="rounded-xl bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300">
						Someone from our team will reply here. We'll send you a notification
						when they do.
					</p>
				)}
				{ticket?.status === "resolved" && (
					<p className="rounded-xl bg-muted p-3 text-xs text-muted-foreground">
						This conversation is marked as solved. Send a message to reopen it.
					</p>
				)}
				<div ref={bottomRef} />
			</div>

			{ticket?.status === "open" && hasAssistantReply && !awaitingReply && (
				<div className="flex flex-wrap justify-center gap-2 px-4 pb-2">
					<Button
						variant="outline"
						size="sm"
						disabled={escalate.isPending}
						onClick={() => escalate.mutate()}>
						<UserRound />
						Talk to a person
					</Button>
					<Button
						variant="outline"
						size="sm"
						disabled={close.isPending}
						onClick={() => close.mutate()}>
						<CheckCircle2 />
						That solved it
					</Button>
				</div>
			)}

			<SupportComposer
				onSend={send}
				sending={sendMessage.isPending}
			/>
		</div>
	)
}
