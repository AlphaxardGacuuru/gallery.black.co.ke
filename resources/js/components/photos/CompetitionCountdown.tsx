import { useEffect, useMemo, useState } from "react"

function timeLeft(endsAt: string) {
	const diff = new Date(endsAt).getTime() - Date.now()

	if (diff <= 0) {
		return null
	}

	const totalSeconds = Math.floor(diff / 1000)

	return {
		days: Math.floor(totalSeconds / 86_400),
		hours: Math.floor((totalSeconds % 86_400) / 3_600),
		minutes: Math.floor((totalSeconds % 3_600) / 60),
		seconds: totalSeconds % 60,
	}
}

function Segment({ value, label }: { value: number; label: string }) {
	return (
		<div className="flex flex-col items-center">
			<span className="tabular-nums text-2xl font-semibold sm:text-3xl">
				{String(value).padStart(2, "0")}
			</span>
			<span className="text-[11px] uppercase tracking-wide text-muted-foreground">
				{label}
			</span>
		</div>
	)
}

export function CompetitionCountdown({ endsAt }: { endsAt: string }) {
	const [now, setNow] = useState(() => Date.now())

	useEffect(() => {
		const interval = setInterval(() => setNow(Date.now()), 1000)
		return () => clearInterval(interval)
	}, [])

	const remaining = useMemo(() => timeLeft(endsAt), [endsAt, now])

	if (!remaining) {
		return (
			<div className="rounded-xl border bg-muted px-4 py-3 text-sm font-medium">
				This week&apos;s challenge just wrapped up — check back soon.
			</div>
		)
	}

	return (
		<div className="flex items-center gap-4 rounded-xl border bg-card px-5 py-4 shadow-sm sm:gap-6">
			<Segment
				value={remaining.days}
				label="Days"
			/>
			<Segment
				value={remaining.hours}
				label="Hrs"
			/>
			<Segment
				value={remaining.minutes}
				label="Min"
			/>
			<Segment
				value={remaining.seconds}
				label="Sec"
			/>
		</div>
	)
}
