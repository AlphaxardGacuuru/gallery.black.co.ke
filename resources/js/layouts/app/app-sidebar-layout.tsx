import { AppBottomNav } from "@/components/app-bottom-nav"
import { AppContent } from "@/components/app-content"
import { AppShell } from "@/components/app-shell"
import { AppSidebar } from "@/components/app-sidebar"
import { AppSidebarHeader } from "@/components/app-sidebar-header"
import { isConversationShowRoute, shouldHideBottomNav } from "@/lib/bottom-nav"
import { cn } from "@/lib/utils"
import type { AppLayoutProps } from "@/types"

export default function AppSidebarLayout({
	children,
	breadcrumbs = [],
}: AppLayoutProps) {
	const hideBottomNav = shouldHideBottomNav()
	const hideSidebarHeader = isConversationShowRoute()

	return (
		<AppShell variant="sidebar">
			<AppContent
				variant="sidebar"
				className={cn(
					"bg-transparent md:pb-0",
					hideBottomNav ? "pb-0" : "pb-24"
				)}>
				{!hideSidebarHeader && (
					<AppSidebarHeader
						breadcrumbs={breadcrumbs}
						variant="floating"
					/>
				)}
				<div className="flex flex-1 flex-col gap-4 overflow-x-hidden p-4">
					{children}
				</div>
			</AppContent>
			<AppSidebar />
			<AppBottomNav />
		</AppShell>
	)
}
