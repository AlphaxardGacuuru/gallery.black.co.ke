import { useApp } from "@/contexts/AppContext"
import type { ReactNode } from "react"
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

	const backdropLines = (
		<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
			<div className="absolute inset-0 bg-[repeating-linear-gradient(45deg,color-mix(in_oklch,var(--primary)_10%,transparent)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:bg-[repeating-linear-gradient(45deg,color-mix(in_oklch,var(--primary)_18%,transparent)_0_1px,transparent_1px_12px)] dark:opacity-[0.16]" />
			<div className="absolute inset-0 bg-[repeating-linear-gradient(135deg,color-mix(in_oklch,var(--primary)_10%,transparent)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:bg-[repeating-linear-gradient(135deg,color-mix(in_oklch,var(--primary)_18%,transparent)_0_1px,transparent_1px_12px)] dark:opacity-[0.16]" />
		</div>
	)

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
