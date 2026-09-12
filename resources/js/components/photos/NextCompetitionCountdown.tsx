import { useEffect, useState } from "react"
import { FlipClock } from "@/components/photos/FlipClock"
import { cn } from "@/lib/utils"

export function NextCompetitionCountdown({
	startsAt,
	className,
}: {
	startsAt: string
	className?: string
}) {
	const [started, setStarted] = useState(false)

	useEffect(() => {
		setStarted(false)
	}, [startsAt])

	if (started) {
		return (
			<div className="rounded-xl border bg-muted px-4 py-3 text-sm font-medium">
				This week&apos;s challenge is starting now, check back in a moment.
			</div>
		)
	}

	return (
		<FlipClock
			target={startsAt}
			onComplete={() => setStarted(true)}
			className={cn("tick-accent-amber", className)}
		/>
	)
}
