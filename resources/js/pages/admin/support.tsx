import { ArrowLeft, CheckCircle2, Bot, Phone } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import Heading from "@/components/heading"
import {
	SupportComposer,
	SupportMessageBubble,
	SupportStatusBadge,
	formatSupportTime,
} from "@/components/support/support-message"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { cn } from "@/lib/utils"
import {
	useAdminSupportTicket,
	useAdminSupportTickets,
	useReplyToSupportTicket,
	useUpdateSupportTicketStatus,
} from "@/queries/admin"
import type { SupportTicketStatus } from "@/queries/support"

const FILTERS: { status: SupportTicketStatus | null; label: string }[] = [
	{ status: "needs_agent", label: "Needs reply" },
	{ status: "in_progress", label: "With team" },
	{ status: "open", label: "Assistant" },
	{ status: "resolved", label: "Solved" },
	{ status: null, label: "All" },
]

function TicketList({
	selectedId,
	onSelect,
}: {
	selectedId: string | null
	onSelect: (id: string) => void
}) {
	const [status, setStatus] = useState<SupportTicketStatus | null>("needs_agent")
	const [page, setPage] = useState(1)
	const { data, isLoading } = useAdminSupportTickets(status, page)

	return (
		<Card className="flex min-h-0 flex-col gap-0 overflow-hidden p-0">
			<div className="flex flex-wrap gap-1.5 border-b p-3">
				{FILTERS.map((filter) => {
					const count = filter.status ? data?.meta.counts[filter.status] : undefined

					return (
						<button
							key={filter.label}
							type="button"
							onClick={() => {
								setStatus(filter.status)
								setPage(1)
							}}
							className={cn(
								"rounded-full border px-3 py-1 text-xs transition-colors",
								status === filter.status
									? "border-primary bg-primary text-primary-foreground"
									: "hover:bg-muted"
							)}>
							{filter.label}
							{count ? ` (${count})` : ""}
						</button>
					)
				})}
			</div>

			<div className="min-h-0 flex-1 divide-y overflow-y-auto">
				{isLoading &&
					Array.from({ length: 4 }).map((_, index) => (
						<Skeleton
							key={index}
							className="m-3 h-14"
						/>
					))}
				{data?.data.length === 0 && (
					<p className="p-6 text-center text-sm text-muted-foreground">
						Nothing here.
					</p>
				)}
				{data?.data.map((ticket) => (
					<button
						key={ticket.id}
						type="button"
						onClick={() => onSelect(ticket.id)}
						className={cn(
							"w-full p-3 text-left transition-colors hover:bg-muted/60",
							ticket.id === selectedId && "bg-muted"
						)}>
						<div className="flex items-start justify-between gap-2">
							<p className="line-clamp-1 text-sm font-medium">
								{ticket.userName ?? "Unknown user"}
							</p>
							<SupportStatusBadge status={ticket.status} />
						</div>
						<p className="line-clamp-1 text-xs">{ticket.subject}</p>
						{ticket.latestMessage && (
							<p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">
								{ticket.latestMessage.author === "user" ? "" : "↩ "}
								{ticket.latestMessage.body}
							</p>
						)}
						<p className="mt-1 text-[11px] text-muted-foreground">
							{ticket.number} · {formatSupportTime(ticket.lastActivityAt)}
						</p>
					</button>
				))}
			</div>

			{data && data.meta.last_page > 1 && (
				<div className="flex items-center justify-between border-t p-2 text-xs">
					<Button
						variant="ghost"
						size="sm"
						disabled={page <= 1}
						onClick={() => setPage(page - 1)}>
						Previous
					</Button>
					<span className="text-muted-foreground">
						{page} / {data.meta.last_page}
					</span>
					<Button
						variant="ghost"
						size="sm"
						disabled={page >= data.meta.last_page}
						onClick={() => setPage(page + 1)}>
						Next
					</Button>
				</div>
			)}
		</Card>
	)
}

function TicketConversation({ id, onBack }: { id: string; onBack: () => void }) {
	const { data: ticket, isLoading } = useAdminSupportTicket(id)
	const reply = useReplyToSupportTicket(id)
	const updateStatus = useUpdateSupportTicketStatus(id)
	const bottomRef = useRef<HTMLDivElement>(null)
	const messages = ticket?.messages ?? []

	useEffect(() => {
		bottomRef.current?.scrollIntoView({ block: "end" })
	}, [messages.length])

	async function send(message: string) {
		try {
			await reply.mutateAsync(message)
		} catch (error) {
			toast.error("Couldn't send the reply", {
				description: (error as Error).message,
			})
			throw error
		}
	}

	function setStatus(status: SupportTicketStatus, success: string) {
		updateStatus.mutate(status, {
			onSuccess: () => toast.success(success),
			onError: (error) =>
				toast.error("Couldn't update the ticket", { description: error.message }),
		})
	}

	if (isLoading || !ticket) {
		return (
			<Card className="p-4">
				<Skeleton className="h-40" />
			</Card>
		)
	}

	const finished = ticket.status === "resolved" || ticket.status === "closed"

	return (
		<Card className="flex min-h-0 flex-col gap-0 overflow-hidden p-0">
			<div className="flex flex-wrap items-center gap-2 border-b p-3">
				<Button
					variant="ghost"
					size="sm"
					className="lg:hidden"
					onClick={onBack}>
					<ArrowLeft />
				</Button>
				<div className="min-w-0 flex-1">
					<p className="truncate text-sm font-medium">
						{ticket.userName} · {ticket.subject}
					</p>
					<p className="flex items-center gap-1 text-xs text-muted-foreground">
						{ticket.number}
						{ticket.userPhone && (
							<>
								{" · "}
								<Phone className="size-3" />
								{ticket.userPhone}
							</>
						)}
					</p>
				</div>
				<SupportStatusBadge status={ticket.status} />
				{ticket.status !== "open" && !finished && (
					<Button
						variant="outline"
						size="sm"
						disabled={updateStatus.isPending}
						onClick={() => setStatus("open", "Handed back to the assistant")}>
						<Bot />
						Back to assistant
					</Button>
				)}
				{!finished && (
					<Button
						variant="outline"
						size="sm"
						disabled={updateStatus.isPending}
						onClick={() => setStatus("resolved", "Marked as solved")}>
						<CheckCircle2 />
						Mark solved
					</Button>
				)}
			</div>

			<div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
				{messages.map((message) => (
					<SupportMessageBubble
						key={message.id}
						message={message}
						mine={message.author !== "user"}
						authorLabel={
							message.author === "user"
								? (ticket.userName ?? "Customer")
								: message.author === "agent"
									? "You"
									: "Assistant"
						}
					/>
				))}
				<div ref={bottomRef} />
			</div>

			<SupportComposer
				onSend={send}
				sending={reply.isPending}
				maxLength={5000}
				placeholder={`Reply to ${ticket.userName ?? "the customer"}…`}
			/>
		</Card>
	)
}

export default function AdminSupport() {
	const [selectedId, setSelectedId] = useState<string | null>(
		() => new URLSearchParams(window.location.search).get("ticket")
	)

	return (
		<>
			<Head title="Support" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Support"
					description="The assistant answers first. Tickets it hands over, or that customers escalate, land in Needs reply."
				/>

				<div className="grid h-[calc(100dvh-14rem)] min-h-[28rem] grid-cols-1 gap-4 lg:grid-cols-[22rem_1fr]">
					<div className={cn("min-h-0", selectedId ? "hidden lg:flex lg:flex-col" : "flex flex-col")}>
						<TicketList
							selectedId={selectedId}
							onSelect={setSelectedId}
						/>
					</div>
					{selectedId ? (
						<TicketConversation
							key={selectedId}
							id={selectedId}
							onBack={() => setSelectedId(null)}
						/>
					) : (
						<Card className="hidden items-center justify-center text-sm text-muted-foreground lg:flex">
							Pick a conversation
						</Card>
					)}
				</div>
			</div>
		</>
	)
}
