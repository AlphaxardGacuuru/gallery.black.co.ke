import { Breadcrumbs } from "@/components/breadcrumbs"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { useSidebar } from "@/components/ui/sidebar"
import VerifiedBadge from "@/components/verified-badge"
import { useApp } from "@/contexts/AppContext"
import { useInitials } from "@/hooks/use-initials"
import { cn } from "@/lib/utils"
import type { BreadcrumbItem as BreadcrumbItemType } from "@/types"
import PhotoConnectionStatus from "./photos/PhotoConnectionStatus"

export function AppSidebarHeader({
	breadcrumbs = [],
	variant = "default",
}: {
	breadcrumbs?: BreadcrumbItemType[]
	variant?: "default" | "floating"
}) {
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
				<Breadcrumbs breadcrumbs={breadcrumbs} />
			</div>

			<PhotoConnectionStatus />

			<button
				type="button"
				onClick={toggleSidebar}
				aria-label="Toggle sidebar"
				className="relative -mr-1 flex size-7 shrink-0 items-center justify-center rounded-full transition-opacity hover:opacity-80 ms-2">
				<Avatar className="size-10">
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
