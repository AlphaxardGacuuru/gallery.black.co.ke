import { Trophy } from "lucide-react"
import { Head } from "@/lib/spa"
import { CompetitionCountdown } from "@/components/photos/CompetitionCountdown"
import { NextCompetitionCountdown } from "@/components/photos/NextCompetitionCountdown"
import { PhotoCard } from "@/components/photos/PhotoCard"
import { UploadPhotoDialog } from "@/components/photos/UploadPhotoDialog"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import { useCurrentCompetition } from "@/queries/photos"

const ORDINALS = [
	"1st",
	"2nd",
	"3rd",
	"4th",
	"5th",
	"6th",
	"7th",
	"8th",
	"9th",
	"10th",
]

// Mirrors the backend's EndPhotoCompetition/notification convention: tiers
// are contiguous and descending, so the first non-positive one means
// nothing further is paid either.
function activePrizeTiers(tiers: number[]): number[] {
	const active: number[] = []

	for (const amount of tiers) {
		if (amount <= 0) {
			break
		}

		active.push(amount)
	}

	return active
}

export default function Compete() {
	const { auth } = useApp()
	const { data, isLoading } = useCurrentCompetition()
	const competition = data?.competition
	const activeCompetition =
		competition?.status === "active" ? competition : undefined
	const hasSubmitted = activeCompetition?.photos.some(
		(photo) => String(photo.userId) === String(auth?.id)
	)
	const prizeTiers = activePrizeTiers(data?.prizeTiers ?? [])
	const runnerUpTiers = prizeTiers.slice(1)

	return (
		<>
			<Head title="This week's challenge" />

			<div className="space-y-6 pb-16 md:pb-20">
				<header className="mb-8 space-y-1">
					<h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
						This week&apos;s challenge
					</h2>
					<p className="text-xl text-muted-foreground">
						{activeCompetition ? (
							<>
								<span className="me-1">
									This week's most liked photo will win
								</span>
								<span className="text-3xl font-bold text-green-600">
									KES {prizeTiers[0]}
								</span>{" "}
							</>
						) : competition ? (
							"Last week's challenge has ended — here's the winning photo. Check back soon for the next one."
						) : (
							"No challenge is running right now, check back soon for your next shot at the prize."
						)}
					</p>
					{activeCompetition && runnerUpTiers.length > 0 && (
						<p className="text-sm text-muted-foreground">
							Plus runner-up prizes:{" "}
							{runnerUpTiers
								.map(
									(amount, index) =>
										`${ORDINALS[index + 1]} place KES ${amount}`
								)
								.join(" · ")}
						</p>
					)}
				</header>

				<div className="mx-auto w-[80vw]">
					{isLoading ? (
						<Skeleton className="h-24 w-full max-w-md" />
					) : activeCompetition ? (
						<>
							<h2 className="mb-4 text-center font-medium uppercase tracking-wide text-muted-foreground">
								Competition Ends in
							</h2>
							<CompetitionCountdown endsAt={activeCompetition.endsAt} />
						</>
					) : data?.nextStartsAt ? (
						<>
							<h2 className="mb-4 text-center font-medium uppercase tracking-wide text-muted-foreground">
								Next Competition starts in
							</h2>
							<NextCompetitionCountdown startsAt={data.nextStartsAt} />
						</>
					) : null}
				</div>

				{isLoading ? (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
						{Array.from({ length: 8 }).map((_, index) => (
							<Skeleton
								key={index}
								className="aspect-square"
							/>
						))}
					</div>
				) : !competition || competition.photos.length === 0 ? (
					<div className="flex flex-col items-center gap-2 rounded-xl border py-16 text-center text-muted-foreground">
						<Trophy className="size-8" />
						<p className="font-medium">No entries yet</p>
						<p className="text-sm">Be the first to submit a photo this week.</p>
					</div>
				) : (
					<div className="grid grid-cols-1 gap-1 sm:grid-cols-3 lg:grid-cols-4">
						{competition.photos.map((photo) => (
							<PhotoCard
								key={photo.id}
								photo={photo}
								canDelete={
									Boolean(activeCompetition) &&
									String(photo.userId) === String(auth?.id)
								}
								canLike={Boolean(activeCompetition)}
							/>
						))}
					</div>
				)}
			</div>

			<UploadPhotoDialog
				disabled={hasSubmitted}
				hasActiveCompetition={Boolean(activeCompetition)}
				hasPhoneNumber={Boolean(auth?.phone)}
			/>
		</>
	)
}
