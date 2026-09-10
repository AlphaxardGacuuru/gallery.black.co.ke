// No routes currently need a full-screen view that the fixed mobile bottom
// nav or sidebar header would get in the way of — kept as hooks for when one
// shows up again.
export function shouldHideBottomNav(): boolean {
	return false
}

export function isConversationShowRoute(): boolean {
	return false
}
