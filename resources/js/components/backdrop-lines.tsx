type Props = {
	animated?: boolean
}

export function BackdropLines({ animated = true }: Props) {
	return (
		<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
			<div className="absolute inset-0 bg-[repeating-linear-gradient(90deg,color-mix(in_oklch,var(--primary)_10%,transparent)_0_1px,transparent_1px_12px)] opacity-[0.5] dark:bg-[repeating-linear-gradient(90deg,color-mix(in_oklch,var(--primary)_18%,transparent)_0_1px,transparent_1px_12px)] dark:opacity-[0.16]" />
			{animated && <div className="backdrop-lines-pulse absolute inset-0" />}
		</div>
	)
}
