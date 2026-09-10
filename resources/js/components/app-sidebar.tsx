import { Link } from "@/components/ui/link"
import { Compass, Download, Image as ImageIcon } from "lucide-react"
import { AdminNav } from "@/components/admin/AdminNav"
import AppLogo from "@/components/app-logo"
import { NavFooter } from "@/components/nav-footer"
import { NavNotifications } from "@/components/nav-notifications"
import { NavUser } from "@/components/nav-user"
import { useApp } from "@/contexts/AppContext"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { usePwaInstall } from "@/hooks/use-pwa-install"
import { ADMIN_EMAIL } from "@/middleware/auth"
import { toUrl } from "@/lib/utils"
import {
	Sidebar,
	SidebarContent,
	SidebarFooter,
	SidebarGroup,
	SidebarHeader,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	useSidebar,
} from "@/components/ui/sidebar"
const HOME_URL = "/compete"
import type { NavItem } from "@/types"

export const mainNavItems: NavItem[] = [
	{
		title: "This week",
		href: "/compete",
		icon: ImageIcon,
	},
	{
		title: "Discover",
		href: "/discover",
		icon: Compass,
	},
]

// Sibling routes like /chats and /chats/archived both start with "/chats",
// so a plain prefix match would leave both nav items active on the archived
// page. Picking the longest matching href resolves the ambiguity in favor
// of the more specific route, and generalizes to any future nav items with
// overlapping prefixes.
export function findActiveNavHref(
	pathname: string,
	items: NavItem[]
): string | null {
	let best: string | null = null

	for (const item of items) {
		const href = toUrl(item.href)
		const matches = pathname === href || pathname.startsWith(`${href}/`)

		if (matches && (best === null || href.length > best.length)) {
			best = href
		}
	}

	return best
}

const footerNavItems: NavItem[] = [
	{
		title: "Get App",
		href: "/get-app",
		icon: Download,
	},
]

export function AppSidebar() {
	const { state } = useSidebar()
	const { isInstalled } = usePwaInstall()
	const { auth } = useApp()
	const { currentUrl } = useCurrentUrl()
	const isAdmin = auth?.email === ADMIN_EMAIL
	const activeMainNavHref = findActiveNavHref(currentUrl, mainNavItems)

	return (
		<Sidebar
			side="right"
			collapsible="icon"
			variant="floating">
			<SidebarHeader>
				<div className="flex items-center">
					<SidebarMenu className="min-w-0 flex-1">
						<SidebarMenuItem>
							<SidebarMenuButton
								size="xl"
								asChild>
								<Link href={HOME_URL}>
									{state === "collapsed" ? (
										<AppLogo
											variant="icon"
											className="h-8"
										/>
									) : (
										<AppLogo />
									)}
								</Link>
							</SidebarMenuButton>
						</SidebarMenuItem>
					</SidebarMenu>
				</div>
			</SidebarHeader>

			<SidebarContent>
				<SidebarGroup className="hidden px-2 py-0 md:block">
					<SidebarMenu>
						{mainNavItems.map((item) => (
							<SidebarMenuItem key={item.title}>
								<SidebarMenuButton
									asChild
									isActive={toUrl(item.href) === activeMainNavHref}
									tooltip={item.title}>
									<Link href={toUrl(item.href)}>
										{item.icon && <item.icon />}
										<span>{item.title}</span>
									</Link>
								</SidebarMenuButton>
							</SidebarMenuItem>
						))}
					</SidebarMenu>
				</SidebarGroup>
				{isAdmin && <AdminNav />}
			</SidebarContent>

			<SidebarFooter>
				{!isInstalled && (
					<NavFooter
						items={footerNavItems}
						className="mt-auto"
					/>
				)}
				<NavNotifications />
				<NavUser />
			</SidebarFooter>
		</Sidebar>
	)
}
