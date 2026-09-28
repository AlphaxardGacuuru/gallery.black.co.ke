// A single photo's full-screen view needs the fixed mobile bottom nav and
// the sidebar header out of the way, since neither leaves enough room for
// an edge-to-edge image.
export function isFullScreenRoute(): boolean {
	return /^\/photos\/[^/]+$/.test(window.location.pathname)
}
