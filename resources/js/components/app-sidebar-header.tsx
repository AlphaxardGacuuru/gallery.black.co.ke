import { useConnectionStatus } from "@laravel/echo-react"
import { useRouterState } from "@tanstack/react-router"
import { Breadcrumbs } from "@/components/breadcrumbs"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { useSidebar } from "@/components/ui/sidebar"
import VerifiedBadge from "@/components/verified-badge"
import { useApp } from "@/contexts/AppContext"
import { useInitials } from "@/hooks/use-initials"
import { cn } from "@/lib/utils"
import { useConversation } from "@/queries/chat"
import type { BreadcrumbItem as BreadcrumbItemType } from "@/types"

function connectionLabel(status: string): string {
	switch (status) {
		case "connected":
			return "Online"
		case "connecting":
			return "Connecting…"
		default:
			return "Offline"
	}
}

export function AppSidebarHeader({
	breadcrumbs = [],
	variant = "default",
}: {
	breadcrumbs?: BreadcrumbItemType[]
	variant?: "default" | "floating"
}) {
	const pathname = useRouterState({
		select: (state) => state.location.pathname,
	})
	const conversationId = pathname.match(/^\/chats\/([^/]+)\/show/)?.[1]
	const { data } = useConversation(conversationId ?? null)
	const chatBreadcrumbs: BreadcrumbItemType[] | null = conversationId
		? [
				{ title: "Chats", href: "/chats" },
				{ title: data?.conversation.otherUser?.name ?? "Chat", href: pathname },
			]
		: pathname === "/chats/new"
			? [
					{ title: "Chats", href: "/chats" },
					{ title: "New chat", href: pathname },
				]
			: pathname === "/chats/archived"
				? [
						// { title: "Chats", href: "/chats" },
						{ title: "Archived", href: pathname },
					]
				: pathname === "/chats" || pathname === "/chats/"
					? [{ title: "Chats", href: pathname }]
					: null
	const displayedBreadcrumbs = chatBreadcrumbs ?? breadcrumbs
	const connectionStatus = useConnectionStatus()
	const { auth } = useApp()
	const { toggleSidebar } = useSidebar()
	const getInitials = useInitials()

	return (
		<header
			className={cn(
				"sticky top-0 z-30 flex shrink-0 flex-row items-center gap-2 py-3 text-sidebar-foreground transition-[width,height] ease-linear md:h-16 md:py-0 md:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12",
				variant === "default" &&
					"border-b border-sidebar-border bg-sidebar px-6 md:px-4",
				variant === "floating" &&
					"mx-2 mt-2 rounded-xl border border-white/40 bg-white/34 px-4 shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20"
			)}>
			<div className="min-w-0 flex-1">
				<Breadcrumbs breadcrumbs={displayedBreadcrumbs} />
			</div>

			<span
				className={cn(
					"inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium",
					connectionStatus === "connected"
						? "border-primary/30 bg-primary/10 text-primary dark:text-primary"
						: "border-muted-foreground/20 bg-muted text-muted-foreground"
				)}>
				<span
					className={cn(
						"size-1.5 rounded-full",
						connectionStatus === "connected"
							? "bg-primary"
							: "bg-muted-foreground/50"
					)}
				/>
				{connectionLabel(connectionStatus)}
			</span>

			<button
				type="button"
				onClick={toggleSidebar}
				aria-label="Toggle sidebar"
				className="relative -mr-1 flex size-7 shrink-0 items-center justify-center rounded-full transition-opacity hover:opacity-80">
				<Avatar className="size-7">
					<AvatarImage
						src={auth?.avatar}
						alt={auth?.name}
					/>
					<AvatarFallback className="text-xs">
						{getInitials(auth?.name ?? "")}
					</AvatarFallback>
				</Avatar>
				{auth?.verified && (
					<VerifiedBadge className="absolute -right-0.5 -bottom-0.5 size-3" />
				)}
			</button>
		</header>
	)
}
