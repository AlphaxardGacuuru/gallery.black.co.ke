import { useEffect, useRef } from "react"
import Tick from "@pqina/flip"
import { cn } from "@/lib/utils"

import "@pqina/flip/dist/flip.min.css"

// Laravel serializes datetimes with 6-digit microseconds (".000000Z"), which
// Safari's Date parser rejects (returns Invalid Date) even though Chrome/
// Firefox tolerate it — trim to millisecond precision so this parses
// everywhere.
function parseIsoDate(value: string): Date {
	return new Date(value.replace(/(\.\d{3})\d*Z$/, "$1Z"))
}

type Props = {
	target: string
	onComplete: () => void
	className?: string
}

export function FlipClock({ target, onComplete, className }: Props) {
	const containerRef = useRef<HTMLDivElement>(null)
	const templateRef = useRef<HTMLElement | null>(null)
	const onCompleteRef = useRef(onComplete)
	onCompleteRef.current = onComplete

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

		// StrictMode's dev-only mount/cleanup/mount cycle can leave a stale
		// counter tick from the discarded first pass still in flight. If it
		// fires after cleanup, `tick.value = value` drives Tick's internal
		// "fit" layout code to read the (by then detached) root's
		// clientWidth and throw. This flag makes such stale updates no-ops.
		let cleanedUp = false
		let stopCounter: (() => void) | undefined

		Tick.DOM.create(tickRoot, {
			didInit: (tick) => {
				const counter = Tick.count.down(parseIsoDate(target))

				counter.onupdate = (value) => {
					if (cleanedUp) {
						return
					}
					tick.value = value
				}
				counter.onended = () => onCompleteRef.current()

				stopCounter = () => counter.timer?.stop()
			},
		})

		return () => {
			cleanedUp = true
			stopCounter?.()
			Tick.DOM.destroy(tickRoot)
		}
	}, [target])

	return (
		<div
			ref={containerRef}
			className={cn("tick w-full text-3xl sm:text-4xl lg:w-1/2 mx-auto", className)}>
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
