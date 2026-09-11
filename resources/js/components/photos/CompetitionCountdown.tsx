import { useEffect, useRef, useState } from "react"
import Tick from "@pqina/flip"
import "@pqina/flip/dist/flip.min.css"

// Laravel serializes datetimes with 6-digit microseconds (".000000Z"), which
// Safari's Date parser rejects (returns Invalid Date) even though Chrome/
// Firefox tolerate it — trim to millisecond precision so this parses
// everywhere.
function parseIsoDate(value: string): Date {
	return new Date(value.replace(/(\.\d{3})\d*Z$/, "$1Z"))
}

export function CompetitionCountdown({ endsAt }: { endsAt: string }) {
	const containerRef = useRef<HTMLDivElement>(null)
	const templateRef = useRef<HTMLElement | null>(null)
	const [ended, setEnded] = useState(false)

	useEffect(() => {
		const container = containerRef.current

		if (!container) {
			return
		}

		// Tick.DOM.destroy() removes the element it was given from the DOM
		// entirely (tick.core's destroyer does root.parentNode.removeChild),
		// not just its contents. Handing it our own React-managed ref node
		// would make that node vanish from the document on cleanup and never
		// come back — breaking under React 18 StrictMode's dev-only
		// mount/cleanup/mount cycle. So `container` stays untouched and
		// always attached, and each run gives Tick a fresh clone of the
		// template to own instead.
		if (!templateRef.current) {
			templateRef.current = container.firstElementChild as HTMLElement
		}

		const tickRoot = templateRef.current.cloneNode(true) as HTMLElement
		container.replaceChildren(tickRoot)

		setEnded(false)

		let stopCounter: (() => void) | undefined

		Tick.DOM.create(tickRoot, {
			didInit: (tick) => {
				const counter = Tick.count.down(parseIsoDate(endsAt))

				counter.onupdate = (value) => {
					tick.value = value
				}
				counter.onended = () => setEnded(true)

				stopCounter = () => counter.timer?.stop()
			},
		})

		return () => {
			stopCounter?.()
			Tick.DOM.destroy(tickRoot)
		}
	}, [endsAt])

	if (ended) {
		return (
			<div className="rounded-xl border bg-muted px-4 py-3 text-sm font-medium">
				This week&apos;s challenge just wrapped up, check back soon.
			</div>
		)
	}

	return (
		<div
			ref={containerRef}
			className="tick w-full text-3xl sm:text-4xl lg:w-1/2 mx-auto">
			<div
				data-repeat="true"
				data-layout="horizontal center fit"
				data-transform="preset(d, h, m, s) -> delay">
				<div className="mx-1.5 text-center">
					<div
						data-key="value"
						data-repeat="true"
						data-transform="pad(00) -> split -> delay">
						<span data-view="flip" />
					</div>
					<span
						data-key="label"
						data-view="text"
						className="mt-1 block text-[15px] lg:text-[30px] font-medium uppercase tracking-wide text-muted-foreground"
					/>
				</div>
			</div>
		</div>
	)
}
