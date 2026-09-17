/// <reference lib="webworker" />
import { cleanupOutdatedCaches, precacheAndRoute } from "workbox-precaching"
import { registerRoute } from "workbox-routing"
import {
	CacheFirst,
	NetworkFirst,
	NetworkOnly,
	StaleWhileRevalidate,
} from "workbox-strategies"
import { ExpirationPlugin } from "workbox-expiration"
import { BackgroundSyncPlugin } from "workbox-background-sync"

declare const self: ServiceWorkerGlobalScope

// ─── Precache Vite build assets ───────────────────────────────────────────────
// The build manifest is injected here by vite-plugin-pwa at build time.
precacheAndRoute(self.__WB_MANIFEST)
cleanupOutdatedCaches()

// Wait for the client's explicit "Refresh" click (app.tsx's
// wb.messageSkipWaiting()) instead of calling self.skipWaiting()
// unconditionally here — that would let every new version claim control of
// already-open tabs the instant it finishes installing, without ever
// populating registration.waiting, which is what the "new version
// available" toast in app.tsx depends on to fire at all.
self.addEventListener("message", (event) => {
	if (event.data?.type === "SKIP_WAITING") {
		self.skipWaiting()
	}
})
self.addEventListener("activate", () => self.clients.claim())

// ─── Caching strategies ───────────────────────────────────────────────────────

// Static assets from the Vite build: cache-first, valid for 30 days.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin && url.pathname.startsWith("/build/"),
	new CacheFirst({
		cacheName: "build-assets",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 120,
				maxAgeSeconds: 30 * 24 * 60 * 60,
			}),
		],
	})
)

// Favicons and PWA icons: cache-first, valid for 30 days.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		/\.(png|ico|svg)$/.test(url.pathname),
	new CacheFirst({
		cacheName: "static-icons",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 20,
				maxAgeSeconds: 30 * 24 * 60 * 60,
			}),
		],
	})
)

// Web app manifest: network-first. A long-lived cache here would pin the
// app name/icons shown in the install prompt to whatever they were on the
// first visit, surviving rebrands for up to the cache's expiry.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		url.pathname.endsWith(".webmanifest"),
	new NetworkFirst({
		cacheName: "webmanifest",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 1,
				maxAgeSeconds: 24 * 60 * 60,
			}),
		],
	})
)

// The current competition reflects the viewer's own just-submitted,
// just-liked, or just-deleted photos, so it can't tolerate the up-to-5-
// -minute staleness stale-while-revalidate allows below — that strategy
// serves whatever's cached immediately, even a snapshot from moments
// before a mutation the viewer just made, undoing an optimistic UI update
// until the next revalidation happens to land. NetworkFirst here means a
// refetch (whether from invalidateQueries after a mutation or the query's
// own refetchInterval) actually gets live data, only falling back to the
// cache if the network request fails.
registerRoute(
	({ url }) =>
		url.origin === self.location.origin && url.pathname === "/api/photos/current",
	new NetworkFirst({ cacheName: "api-current-competition" })
)

// Read API routes: stale-while-revalidate so the last-fetched response
// renders immediately — offline or not — while a fresh copy is fetched in
// the background for next time. Auth-sensitive and current-competition
// routes are intentionally excluded above/below.
const STALE_WHILE_REVALIDATE_APIS = ["/api/notifications", "/api/photos"]

registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		STALE_WHILE_REVALIDATE_APIS.some((prefix) =>
			url.pathname.startsWith(prefix)
		),
	new StaleWhileRevalidate({
		cacheName: "api-stale",
		plugins: [
			new ExpirationPlugin({
				maxEntries: 60,
				maxAgeSeconds: 5 * 60, // 5 minutes max staleness
			}),
		],
	})
)

// Auth routes: always network-first, never serve stale data.
const NETWORK_ONLY_APIS = ["/api/auth", "/api/broadcasting"]

registerRoute(
	({ url }) =>
		url.origin === self.location.origin &&
		NETWORK_ONLY_APIS.some((prefix) => url.pathname.startsWith(prefix)),
	new NetworkFirst({ cacheName: "api-auth" })
)

// ─── Background sync for mutation requests ────────────────────────────────────
// Queues PUT/DELETE/POST requests that fail due to lost connectivity and
// replays them automatically once the connection is restored.
const bgSyncPlugin = new BackgroundSyncPlugin("mutation-queue", {
	maxRetentionTime: 24 * 60, // Retry for up to 24 hours
})

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"PUT"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"DELETE"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"POST"
)

registerRoute(
	({ url, request }) =>
		url.origin === self.location.origin &&
		url.pathname.startsWith("/api/") &&
		["PUT", "DELETE", "POST", "PATCH"].includes(request.method),
	new NetworkOnly({ plugins: [bgSyncPlugin] }),
	"PATCH"
)

// ─── Web push notifications ───────────────────────────────────────────────────

type PushAction = { action: string; title: string }

type PushNotificationData = { url?: string }

type PushPayload = {
	title?: string
	body?: string
	icon?: string
	badge?: string
	tag?: string
	renotify?: boolean
	actions?: PushAction[]
	data?: PushNotificationData
}

self.addEventListener("push", (event) => {
	if (!event.data) {
		return
	}

	const payload: PushPayload = event.data.json()

	// `renotify` and `actions` are real, supported NotificationOptions
	// fields missing from TypeScript's bundled DOM lib — widen the type
	// locally instead of casting away the rest of the options' checking.
	const options: NotificationOptions & {
		renotify?: boolean
		actions?: PushAction[]
	} = {
		body: payload.body,
		icon: payload.icon ?? "/notification-badge-192x192.png",
		badge: payload.badge ?? "/notification-badge-192x192.png",
		tag: payload.tag,
		renotify: payload.renotify,
		actions: payload.actions,
		data: payload.data,
	}

	event.waitUntil(
		self.registration.showNotification(
			payload.title ?? "New notification",
			options
		)
	)
})

// Opens the notification's target URL (or focuses it if already open in
// some tab) — the fallback for a plain click.
function openTargetUrl(url: string) {
	return self.clients
		.matchAll({ type: "window", includeUncontrolled: true })
		.then((clients) => {
			const targetPath = new URL(url, self.location.origin).pathname
			const existing = clients.find(
				(client) => new URL(client.url).pathname === targetPath
			)

			if (existing) {
				return existing.focus()
			}

			return self.clients.openWindow(new URL(url, self.location.origin).href)
		})
}

self.addEventListener("notificationclick", (event) => {
	event.notification.close()

	const data = event.notification.data as PushNotificationData | undefined

	if (!data?.url) {
		return
	}

	event.waitUntil(openTargetUrl(data.url))
})

// ─── SPA navigation fallback ──────────────────────────────────────────────────
// All navigate requests that don't match a precached URL fall back to /index.php
// so TanStack Router handles routing on the client side.
registerRoute(
	({ request }) => request.mode === "navigate",
	new NetworkFirst({
		cacheName: "navigation",
		plugins: [new ExpirationPlugin({ maxEntries: 1, maxAgeSeconds: 60 })],
	})
)
