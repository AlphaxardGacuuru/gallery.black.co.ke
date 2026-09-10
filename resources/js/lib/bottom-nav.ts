// Routes where the fixed mobile bottom nav gets in the way of a full-screen,
// task-focused view (an open conversation's own composer sits at the exact
// same screen edge) and should be hidden instead of overlapping.
const HIDDEN_ON = [/^\/chats\/new/, /^\/chats\/[^/]+\/show/]

export function shouldHideBottomNav(pathname: string): boolean {
	return HIDDEN_ON.some((pattern) => pattern.test(pathname))
}

// An open conversation renders its own sticky user-info header (avatar,
// name, presence), so the generic app header is redundant there.
const CONVERSATION_SHOW_PATTERN = /^\/chats\/[^/]+\/show/

export function isConversationShowRoute(pathname: string): boolean {
	return CONVERSATION_SHOW_PATTERN.test(pathname)
}
