import { useApp } from "@/contexts/AppContext"
import type { ReactNode } from "react"
import { BackdropLines } from "@/components/backdrop-lines"
import { FloatingUserAvatar } from "@/components/floating-user-avatar"
import PermissionsOnboardingModal from "@/components/permissions-onboarding-modal"
import { SidebarProvider } from "@/components/ui/sidebar"
import type { AppVariant } from "@/types"

type Props = {
	children: ReactNode
	variant?: AppVariant
}

export function AppShell({ children, variant = "sidebar" }: Props) {
	const { auth } = useApp()

	const shouldRenderFloatingAvatar = Boolean(auth)

	const backdropLines = <BackdropLines animated={false} />

	if (variant === "header") {
		return (
			<div className="relative flex min-h-screen w-full flex-col">
				{backdropLines}
				{children}
				{shouldRenderFloatingAvatar && <FloatingUserAvatar />}
				{auth && <PermissionsOnboardingModal />}
			</div>
		)
	}

	return (
		<div className="relative">
			{backdropLines}
			<SidebarProvider defaultOpen={true}>
				{children}
				{/* {shouldRenderFloatingAvatar && <FloatingUserAvatar />} */}
			</SidebarProvider>
			{auth && <PermissionsOnboardingModal />}
		</div>
	)
}
