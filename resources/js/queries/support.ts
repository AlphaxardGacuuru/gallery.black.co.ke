import {
	type Query,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import Axios from "@/lib/axios"

export type SupportTicketStatus =
	| "open"
	| "needs_agent"
	| "in_progress"
	| "resolved"
	| "closed"

export type SupportMessageAuthor = "user" | "assistant" | "agent"

export type SupportMessage = {
	id: string
	author: SupportMessageAuthor
	body: string
	createdAt: string
}

export type SupportTicket = {
	id: string
	number: string
	userId: string
	userName: string | null
	userAvatar: string | null
	userPhone: string | null
	category: string
	subject: string
	status: SupportTicketStatus
	createdAt: string
	lastActivityAt: string
	/** A reply the customer hasn't opened yet (their own list only). */
	unread?: boolean
	latestMessage?: SupportMessage | null
	messages?: SupportMessage[]
}

/**
 * The assistant replies in a background job, so after the customer writes
 * on a ticket it's still handling, the reply is on its way.
 */
export function isAwaitingReply(ticket: SupportTicket | undefined): boolean {
	const messages = ticket?.messages ?? []

	return ticket?.status === "open" && messages.at(-1)?.author === "user"
}

// Quick while the assistant is typing, slow while waiting on a person
// (who also triggers a push notification), off once it's settled.
function pollInterval(query: Query<SupportTicket>): number | false {
	const ticket = query.state.data

	if (isAwaitingReply(ticket)) {
		return 2_000
	}

	if (ticket?.status === "needs_agent" || ticket?.status === "in_progress") {
		return 20_000
	}

	return false
}

/** What the badge on the floating Feedback button counts. `waiting` is
 *  only set for the admin: tickets waiting on a person. */
export type SupportPending = {
	unread: number
	waiting: number | null
}

export function useSupportPending() {
	return useQuery({
		queryKey: ["support-pending"],
		queryFn: () =>
			Axios.get<{ data: SupportPending }>("api/support-tickets/pending").then(
				(res) => res.data.data
			),
		refetchInterval: 60_000,
	})
}

export function useSupportTickets(enabled = true) {
	return useQuery({
		queryKey: ["support-tickets"],
		queryFn: () =>
			Axios.get<{ data: SupportTicket[] }>("api/support-tickets").then(
				(res) => res.data.data
			),
		enabled,
	})
}

export function useSupportTicket(id: string | null) {
	return useQuery({
		queryKey: ["support-tickets", id],
		queryFn: () =>
			Axios.get<{ data: SupportTicket }>(`api/support-tickets/${id}`).then(
				(res) => res.data.data
			),
		enabled: Boolean(id),
		refetchInterval: pollInterval,
	})
}

/** Every write returns the whole ticket, so drop it straight in the cache. */
function useTicketMutation<Variables>(
	request: (variables: Variables) => Promise<SupportTicket>
) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: request,
		onSuccess: (ticket) => {
			queryClient.setQueryData(["support-tickets", ticket.id], ticket)
			queryClient.invalidateQueries({
				queryKey: ["support-tickets"],
				exact: true,
			})
		},
	})
}

export function useStartSupportTicket() {
	return useTicketMutation((message: string) =>
		Axios.post<{ data: SupportTicket }>("api/support-tickets", {
			message,
		}).then((res) => res.data.data)
	)
}

export function useSendSupportMessage(id: string) {
	return useTicketMutation((message: string) =>
		Axios.post<{ data: SupportTicket }>(`api/support-tickets/${id}/messages`, {
			message,
		}).then((res) => res.data.data)
	)
}

export function useEscalateSupportTicket(id: string) {
	return useTicketMutation<void>(() =>
		Axios.post<{ data: SupportTicket }>(
			`api/support-tickets/${id}/escalate`
		).then((res) => res.data.data)
	)
}

export function useCloseSupportTicket(id: string) {
	return useTicketMutation<void>(() =>
		Axios.post<{ data: SupportTicket }>(`api/support-tickets/${id}/close`).then(
			(res) => res.data.data
		)
	)
}
