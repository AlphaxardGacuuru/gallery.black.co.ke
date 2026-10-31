import { useQuery } from "@tanstack/react-query"
import Axios from "@/lib/axios"

export type Notice = {
	id: string
	/** Hash of the rendered text, sent back with every event. */
	version: string
	icon: string
	title: string
	body: string
	primaryLabel: string
	primaryUrl: string | null
	secondaryLabel: string | null
}

export type NoticeAction =
	| "shown"
	| "primary_click"
	| "secondary_click"
	| "dismissed"

// Fetched once per app load: the server applies the daily limit and gaps,
// so there's nothing to gain by asking again mid-visit.
export function useNextNotice(
	userId: string | number | undefined,
	enabled: boolean
) {
	return useQuery({
		queryKey: ["notices", "next", userId],
		queryFn: () =>
			Axios.get<{ data: Notice | null }>("api/notices/next").then(
				(res) => res.data.data
			),
		enabled: Boolean(userId) && enabled,
		staleTime: Infinity,
		refetchOnWindowFocus: false,
	})
}

export function recordNoticeEvent(notice: Notice, action: NoticeAction) {
	Axios.post(`api/notices/${notice.id}/events`, {
		version: notice.version,
		action,
	}).catch(() => {
		// Tracking must never get in the user's way.
	})
}
