import { BadgeCheck } from "lucide-react"
import type { ReactNode } from "react"
import { cn } from "@/lib/utils"

type Props = {
	className?: string
}

export default function VerifiedBadge({ className }: Props) {
	return (
		<BadgeCheck
			aria-label="Verified"
			className={cn("size-4 fill-primary text-primary-foreground", className)}
		/>
	)
}

/** Wraps an avatar and pins the verified badge to its bottom-right corner.
 *  The badge sits outside the Avatar itself because Avatar clips overflow. */
export function VerifiedAvatar({
	verified,
	children,
	className,
	badgeClassName,
}: {
	verified?: boolean
	children: ReactNode
	className?: string
	badgeClassName?: string
}) {
	return (
		<span className={cn("relative inline-flex shrink-0", className)}>
			{children}
			{verified && (
				<VerifiedBadge
					className={cn("absolute -right-0.5 -bottom-0.5 size-3.5", badgeClassName)}
				/>
			)}
		</span>
	)
}
