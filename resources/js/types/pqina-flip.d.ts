declare module "@pqina/flip" {
	type TickCounter = {
		value: unknown
		timer?: { stop: () => void } | null
		onupdate: (value: unknown) => void
		onended: () => void
	}

	type TickInstance = {
		value: unknown
		destroy: () => void
	}

	type TickDidInit = (tick: TickInstance) => void

	const Tick: {
		DOM: {
			create: (
				element: HTMLElement,
				options?: { didInit?: TickDidInit }
			) => TickInstance
			destroy: (element: HTMLElement) => boolean
		}
		count: {
			down: (due: Date | string) => TickCounter
		}
	}

	export default Tick
}

declare module "@pqina/flip/dist/flip.min.css"
