import type { LucideIcon } from "lucide-react"
import { cn } from "@/lib/utils"
import { Card, CardContent } from "@/components/ui/card"

type Props = {
	label: string
	value: number
	icon: LucideIcon
	tone?: "default" | "success" | "danger" | "warning"
}

const toneClasses: Record<NonNullable<Props["tone"]>, string> = {
	default: "text-foreground bg-muted",
	success: "text-emerald-600 bg-emerald-500/10 dark:text-emerald-400",
	danger: "text-red-600 bg-red-500/10 dark:text-red-400",
	warning: "text-amber-600 bg-amber-500/10 dark:text-amber-400",
}

export default function AdminStatCard({
	label,
	value,
	icon: Icon,
	tone = "default",
}: Props) {
	return (
		<Card>
			<CardContent className="flex justify-between items-center gap-4">
				<div className="min-w-0">
					<p className="text-2xl font-semibold tracking-tight">
						{value.toLocaleString()}
					</p>
					<p className="text-sm leading-tight text-muted-foreground">{label}</p>
				</div>
				<div
					className={cn(
						"flex size-18 shrink-0 items-center justify-center rounded-md",
						toneClasses[tone]
					)}>
					<Icon className="size-8" />
				</div>
			</CardContent>
		</Card>
	)
}
