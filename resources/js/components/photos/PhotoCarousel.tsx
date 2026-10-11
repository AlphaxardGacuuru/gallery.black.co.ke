import { ChevronLeft, ChevronRight } from "lucide-react"
import { useRef, useState } from "react"
import { cn } from "@/lib/utils"

type Slide = { url: string; alt: string }

/**
 * Instagram-style swipeable carousel, built on native CSS scroll-snap
 * rather than a JS carousel library (no embla/swiper dependency exists in
 * this codebase), gives native momentum/touch scrolling for free, with a
 * scroll-position-driven dot indicator and desktop-only prev/next buttons
 * for when there's no touchscreen to swipe with.
 */
export function PhotoCarousel({ slides }: { slides: Slide[] }) {
	const trackRef = useRef<HTMLDivElement>(null)
	const [index, setIndex] = useState(0)

	function handleScroll() {
		const track = trackRef.current

		if (!track || track.clientWidth === 0) {
			return
		}

		setIndex(Math.round(track.scrollLeft / track.clientWidth))
	}

	function goTo(nextIndex: number) {
		const track = trackRef.current

		if (!track) {
			return
		}

		track.scrollTo({ left: nextIndex * track.clientWidth, behavior: "smooth" })
	}

	if (slides.length <= 1) {
		return (
			<img
				src={slides[0]?.url}
				alt={slides[0]?.alt}
				className="max-h-full max-w-full object-contain"
			/>
		)
	}

	return (
		<div className="relative h-full w-full">
			<div
				ref={trackRef}
				onScroll={handleScroll}
				className="flex h-full w-full snap-x snap-mandatory overflow-x-auto overscroll-x-contain [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
				{slides.map((slide, slideIndex) => (
					<div
						key={slide.url}
						className="flex h-full w-full shrink-0 snap-center snap-always items-center justify-center">
						<img
							src={slide.url}
							alt={slide.alt}
							loading={slideIndex === 0 ? "eager" : "lazy"}
							className="max-h-full max-w-full object-contain"
						/>
					</div>
				))}
			</div>

			<div className="pointer-events-none absolute top-3 right-3 rounded-full bg-black/60 px-2 py-0.5 text-xs font-medium text-white">
				{index + 1}/{slides.length}
			</div>

			<div className="pointer-events-none absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
				{slides.map((slide, slideIndex) => (
					<span
						key={slide.url}
						className={cn(
							"size-1.5 rounded-full",
							slideIndex === index ? "bg-white" : "bg-white/40"
						)}
					/>
				))}
			</div>

			<button
				type="button"
				aria-label="Previous photo"
				disabled={index === 0}
				onClick={() => goTo(index - 1)}
				className="absolute top-1/2 left-2 hidden -translate-y-1/2 rounded-full bg-black/50 p-1.5 text-white disabled:opacity-0 md:flex">
				<ChevronLeft className="size-5" />
			</button>
			<button
				type="button"
				aria-label="Next photo"
				disabled={index === slides.length - 1}
				onClick={() => goTo(index + 1)}
				className="absolute top-1/2 right-2 hidden -translate-y-1/2 rounded-full bg-black/50 p-1.5 text-white disabled:opacity-0 md:flex">
				<ChevronRight className="size-5" />
			</button>
		</div>
	)
}
