import { BadgeCheck } from "lucide-react"
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
