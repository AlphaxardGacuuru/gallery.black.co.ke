type Props = {
	animated?: boolean
}

// Each drop falls along one of the background's vertical lines (12px apart)
// so it stays perfectly aligned with the grid. Delays and durations are
// hand-spread rather than evenly spaced, so the drops read as scattered
// rainfall instead of one wave moving in lockstep.
const RAIN_DROPS = [
	{ line: 3, delay: 0, duration: 5.5 },
	{ line: 9, delay: 1.8, duration: 6.2 },
	{ line: 14, delay: 0.6, duration: 4.8 },
	{ line: 21, delay: 3.4, duration: 7 },
	{ line: 27, delay: 2.1, duration: 5.1 },
	{ line: 35, delay: 4.6, duration: 6.6 },
	{ line: 42, delay: 1.1, duration: 5.8 },
	{ line: 50, delay: 3.9, duration: 4.5 },
	{ line: 58, delay: 0.3, duration: 6.9 },
	{ line: 66, delay: 2.7, duration: 5.4 },
	{ line: 75, delay: 4.2, duration: 6.1 },
	{ line: 83, delay: 1.4, duration: 4.9 },
	{ line: 91, delay: 3.1, duration: 6.4 },
	{ line: 100, delay: 0.9, duration: 5.7 },
	{ line: 110, delay: 2.4, duration: 6.8 },
	{ line: 120, delay: 4.9, duration: 5.2 },
	{ line: 132, delay: 1.6, duration: 6.3 },
	{ line: 145, delay: 3.6, duration: 5.6 },
	{ line: 158, delay: 0.1, duration: 6 },
	{ line: 170, delay: 2.9, duration: 5.9 },
]

export function BackdropLines({ animated = true }: Props) {
	return (
		<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
			<div className="absolute inset-0 bg-[repeating-linear-gradient(90deg,color-mix(in_oklch,var(--primary)_10%,transparent)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:bg-[repeating-linear-gradient(90deg,color-mix(in_oklch,var(--primary)_18%,transparent)_0_1px,transparent_1px_12px)] dark:opacity-[0.16]" />
			{animated && (
				<div className="rain-drops absolute inset-0">
					{RAIN_DROPS.map((drop, index) => (
						<span
							key={index}
							className="rain-drop"
							style={{
								left: `${drop.line * 12}px`,
								animationDelay: `${drop.delay}s`,
								animationDuration: `${drop.duration}s`,
							}}
						/>
					))}
				</div>
			)}
		</div>
	)
}
