import { useEffect, useState } from "react"
import { FlipClock } from "@/components/photos/FlipClock"

export function NextCompetitionCountdown({ startsAt }: { startsAt: string }) {
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
			className="tick-accent-amber"
		/>
	)
}
