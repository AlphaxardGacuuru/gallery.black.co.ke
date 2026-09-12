import { useEffect, useState } from "react"
import { FlipClock } from "@/components/photos/FlipClock"

export function CompetitionCountdown({ endsAt }: { endsAt: string }) {
	const [ended, setEnded] = useState(false)

	useEffect(() => {
		setEnded(false)
	}, [endsAt])

	if (ended) {
		return (
			<div className="rounded-xl border bg-muted px-4 py-3 text-sm font-medium">
				This week&apos;s challenge just wrapped up, check back soon.
			</div>
		)
	}

	return (
		<FlipClock
			target={endsAt}
			onComplete={() => setEnded(true)}
		/>
	)
}
