import { SendHorizontal } from "lucide-react"
import { type FormEvent, type KeyboardEvent, useState } from "react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import { cn } from "@/lib/utils"
import type {
	SupportMessage,
	SupportMessageAuthor,
	SupportTicketStatus,
} from "@/queries/support"

const AUTHOR_LABELS: Record<SupportMessageAuthor, string> = {
	user: "You",
	assistant: "Assistant",
	agent: "Support team",
}

const STATUS_LABELS: Record<SupportTicketStatus, string> = {
	open: "Assistant",
	needs_agent: "Waiting for team",
	in_progress: "With support team",
	resolved: "Solved",
	closed: "Closed",
}

export function supportStatusLabel(status: SupportTicketStatus): string {
	return STATUS_LABELS[status] ?? status
}

export function SupportStatusBadge({
	status,
}: {
	status: SupportTicketStatus
}) {
	return (
		<Badge
			variant="outline"
			className={cn(
				"shrink-0",
				status === "needs_agent" &&
					"border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-400",
				status === "in_progress" &&
					"border-transparent bg-sky-500/15 text-sky-700 dark:text-sky-400",
				(status === "resolved" || status === "closed") &&
					"text-muted-foreground"
			)}>
			{supportStatusLabel(status)}
		</Badge>
	)
}

export function formatSupportTime(iso: string): string {
	const date = new Date(iso)
	const sameDay = date.toDateString() === new Date().toDateString()

	return date.toLocaleString(undefined, {
		...(sameDay ? {} : { day: "numeric", month: "short" }),
		hour: "numeric",
		minute: "2-digit",
	})
}

/**
 * One chat bubble. `mine` puts it on the right in the primary colour: the
 * customer's own messages in the app, the team's replies in the admin inbox.
 */
export function SupportMessageBubble({
	message,
	mine,
	authorLabel,
}: {
	message: SupportMessage
	mine: boolean
	authorLabel?: string
}) {
	return (
		<div className={cn("flex flex-col gap-1", mine ? "items-end" : "items-start")}>
			<div
				className={cn(
					"max-w-[85%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap break-words",
					mine
						? "rounded-br-sm bg-primary text-primary-foreground"
						: "rounded-bl-sm bg-muted text-foreground",
					message.author === "agent" &&
						!mine &&
						"border border-sky-500/30 bg-sky-500/10"
				)}>
				{message.body}
			</div>
			<span className="px-1 text-[11px] text-muted-foreground">
				{authorLabel ?? AUTHOR_LABELS[message.author]} ·{" "}
				{formatSupportTime(message.createdAt)}
			</span>
		</div>
	)
}

export function TypingIndicator() {
	return (
		<div
			className="flex items-start"
			aria-label="The assistant is typing">
			<div className="flex gap-1 rounded-2xl rounded-bl-sm bg-muted px-3.5 py-3">
				{[0, 150, 300].map((delay) => (
					<span
						key={delay}
						className="size-1.5 animate-bounce rounded-full bg-muted-foreground/60"
						style={{ animationDelay: `${delay}ms` }}
					/>
				))}
			</div>
		</div>
	)
}

/** Enter sends, Shift+Enter adds a new line. */
export function SupportComposer({
	onSend,
	sending,
	placeholder = "Type your message…",
	maxLength = 2000,
	autoFocus = false,
}: {
	onSend: (message: string) => Promise<unknown> | void
	sending: boolean
	placeholder?: string
	maxLength?: number
	autoFocus?: boolean
}) {
	const [value, setValue] = useState("")

	async function submit(event?: FormEvent) {
		event?.preventDefault()

		const message = value.trim()

		if (!message || sending) {
			return
		}

		setValue("")

		try {
			await onSend(message)
		} catch {
			// Keep what they wrote so they can try again.
			setValue(message)
		}
	}

	function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
		if (event.key === "Enter" && !event.shiftKey) {
			event.preventDefault()
			submit()
		}
	}

	return (
		<form
			onSubmit={submit}
			className="flex items-end gap-2 border-t bg-background p-3">
			<textarea
				className="max-h-32 min-h-10 flex-1 resize-none rounded-xl border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
				rows={1}
				value={value}
				maxLength={maxLength}
				placeholder={placeholder}
				autoFocus={autoFocus}
				onChange={(event) => setValue(event.target.value)}
				onKeyDown={handleKeyDown}
			/>
			<Button
				type="submit"
				size="icon"
				className="size-10 shrink-0 rounded-xl"
				disabled={sending || !value.trim()}
				aria-label="Send">
				{sending ? <Spinner className="size-4" /> : <SendHorizontal />}
			</Button>
		</form>
	)
}
