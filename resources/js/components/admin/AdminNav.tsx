import {
	Activity,
	ChevronRight,
	Coins,
	Gauge,
	KeyRound,
	LayoutDashboard,
	LifeBuoy,
	Megaphone,
	Send,
	ShieldCheck,
	Telescope,
	Trophy,
	UserPlus,
	Users,
	type LucideIcon,
} from "lucide-react"
import { useState } from "react"
import { Link } from "@/components/ui/link"
import axios from "@/lib/axios"
import toast from "@/lib/toast"
import {
	Collapsible,
	CollapsibleContent,
	CollapsibleTrigger,
} from "@/components/ui/collapsible"
import {
	SidebarGroup,
	SidebarMenu,
	SidebarMenuButton,
	SidebarMenuItem,
	SidebarMenuSub,
	SidebarMenuSubButton,
	SidebarMenuSubItem,
} from "@/components/ui/sidebar"
import { useApp } from "@/contexts/AppContext"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { clearAuth } from "@/middleware/auth"
import { logout } from "@/routes"

// Telescope, Horizon and Pulse are Laravel-rendered dashboards, opened in a
// new tab so the app stays open behind them.
type AdminItem = {
	title: string
	href: string
	icon: LucideIcon
	newTab?: boolean
	/** A flag on the auth user (from /api/auth) the item needs to be shown. */
	ability?: "can_view_ai_usage"
}

const ADMIN_ITEMS: AdminItem[] = [
	{ title: "Dashboard", href: "/admin/dashboard", icon: LayoutDashboard },
	{ title: "Users", href: "/admin/users", icon: Users },
	{ title: "Photo challenge", href: "/admin/photo-competitions", icon: Trophy },
	{ title: "Payouts", href: "/admin/kopokopo-transfers", icon: Send },
	{ title: "Referrals", href: "/admin/referrals", icon: UserPlus },
	{ title: "Notices", href: "/admin/notices", icon: Megaphone },
	{ title: "Support", href: "/admin/support", icon: LifeBuoy },
	{
		title: "AI usage",
		href: "/admin/ai-usage",
		icon: Coins,
		ability: "can_view_ai_usage",
	},
	{ title: "Telescope", href: "/telescope", icon: Telescope, newTab: true },
	{ title: "Horizon", href: "/horizon", icon: Activity, newTab: true },
	{ title: "Pulse", href: "/pulse", icon: Gauge, newTab: true },
]

export function AdminNav() {
	const { auth } = useApp()
	const { isCurrentOrParentUrl } = useCurrentUrl()
	// Items with an ability are only listed when /api/auth grants it.
	const items = ADMIN_ITEMS.filter(
		(item) => !item.ability || Boolean(auth?.[item.ability])
	)
	const isActive = items.some((item) => isCurrentOrParentUrl(item.href))
	const [switching, setSwitching] = useState(false)

	// The signed link has to be fetched while still signed in, then the
	// session is ended and the page is opened with a full load, so the
	// server checks the signature.
	const handlePasswordLogin = async () => {
		setSwitching(true)

		try {
			const link = await axios.post("api/admin/password-login-link")
			const route = logout()

			await axios
				.request({ url: route.url, method: route.method })
				.catch(() => undefined)

			clearAuth()
			window.location.assign(link.data.data)
		} catch {
			toast.error("Unable to open the password login page")
			setSwitching(false)
		}
	}

	return (
		<SidebarGroup className="px-3 py-0">
			<SidebarMenu>
				<Collapsible
					defaultOpen={isActive}
					className="group/collapsible">
					<SidebarMenuItem>
						<CollapsibleTrigger asChild>
							<SidebarMenuButton className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100">
								<ShieldCheck className="h-5 w-5" />
								<span>Admin</span>
								<ChevronRight className="ml-auto size-4 transition-transform group-data-[state=open]/collapsible:rotate-90" />
							</SidebarMenuButton>
						</CollapsibleTrigger>
						<CollapsibleContent>
							<SidebarMenuSub>
								{items.map((item) => (
									<SidebarMenuSubItem key={item.href}>
										<SidebarMenuSubButton
											asChild
											isActive={isCurrentOrParentUrl(item.href)}>
											<Link
												href={item.href}
												target={item.newTab ? "_blank" : undefined}
												rel={item.newTab ? "noopener" : undefined}>
												<item.icon className="size-4" />
												<span>{item.title}</span>
											</Link>
										</SidebarMenuSubButton>
									</SidebarMenuSubItem>
								))}
								<SidebarMenuSubItem>
									<SidebarMenuSubButton asChild>
										<button
											type="button"
											className="w-full cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
											onClick={handlePasswordLogin}
											disabled={switching}>
											<KeyRound className="size-4" />
											<span>Log in with password</span>
										</button>
									</SidebarMenuSubButton>
								</SidebarMenuSubItem>
							</SidebarMenuSub>
						</CollapsibleContent>
					</SidebarMenuItem>
				</Collapsible>
			</SidebarMenu>
		</SidebarGroup>
	)
}
