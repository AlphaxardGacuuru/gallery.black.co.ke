import Axios from "@/lib/axios"

/** The events the browser records. The rest are recorded on the server. */
export type ClientEvent = "page.viewed" | "photo.shared" | "referral.shared"

type TrackOptions = {
	photoId?: string
	/** Where it happened, e.g. share_sheet or copied_link. */
	source?: string
	properties?: Record<string, string | null>
}

const ANONYMOUS_ID_KEY = "analyticsAnonymousId"

/**
 * A random id this browser keeps, so a guest's page views can be followed
 * and linked to their account once they sign in. Falls back to a new id
 * each time when storage is blocked, e.g. in a private window.
 */
function anonymousId(): string {
	try {
		const stored = localStorage.getItem(ANONYMOUS_ID_KEY)

		if (stored) {
			return stored
		}

		const id = crypto.randomUUID()
		localStorage.setItem(ANONYMOUS_ID_KEY, id)

		return id
	} catch {
		return crypto.randomUUID()
	}
}

/**
 * Record a usage event for analytics (see App\Enums\UserEventName). Fire
 * and forget: a failed request never gets in the user's way.
 */
export function track(event: ClientEvent, options: TrackOptions = {}): void {
	Axios.post("api/events", {
		event,
		anonymousId: anonymousId(),
		path: window.location.pathname,
		...options,
	}).catch(() => {
		// Analytics only, nothing to tell the user.
	})
}
