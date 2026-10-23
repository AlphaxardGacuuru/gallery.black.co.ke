import { Trophy } from "lucide-react"
import { Head } from "@/lib/spa"
import { AvatarPreviewDialog } from "@/components/avatar-preview-dialog"
import Heading from "@/components/heading"
import VerifiedBadge from "@/components/verified-badge"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useInitials } from "@/hooks/use-initials"
import { cn } from "@/lib/utils"
import { type LeaderboardEntry, useLeaderboard } from "@/queries/photos"

const ORDINALS = ["1st", "2nd", "3rd", "4th", "5th", "6th", "7th", "8th", "9th", "10th"]

export default function Leaderboard() {
	const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
		useLeaderboard()

	const entries = data?.pages.flatMap((page) => page.data) ?? []

	return (
		<>
			<Head title="Leaderboard" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Leaderboard"
					description="Ranked by 1st places, then 2nd places, and so on"
				/>

				{isLoading ? (
					<div className="space-y-2">
						{Array.from({ length: 8 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-16 rounded-xl"
							/>
						))}
					</div>
				) : entries.length === 0 ? (
					<div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center text-muted-foreground">
						<Trophy className="size-8" />
						<p className="font-medium">Nobody ranked yet</p>
						<p className="text-sm">
							Everyone who posts a photo will show up here.
						</p>
					</div>
				) : (
					<>
						<ol className="space-y-2">
							{entries.map((entry) => (
								<LeaderboardRow
									key={entry.userId}
									entry={entry}
								/>
							))}
						</ol>

						{hasNextPage && (
							<div className="flex justify-center">
								<Button
									variant="outline"
									disabled={isFetchingNextPage}
									onClick={() => fetchNextPage()}>
									{isFetchingNextPage ? "Loading…" : "Load more"}
								</Button>
							</div>
						)}
					</>
				)}
			</div>
		</>
	)
}

function LeaderboardRow({ entry }: { entry: LeaderboardEntry }) {
	const getInitials = useInitials()

	// Only the positions this user actually finished at, best first.
	const placed = entry.placements
		.map((count, index) => ({ position: index + 1, count }))
		.filter(({ count }) => count > 0)

	return (
		<li
			className={cn(
				"flex items-center gap-3 rounded-xl border bg-card p-3 shadow-sm",
				entry.rank === 1 && placed.length > 0 && "ring-1 ring-amber-400"
			)}>
			<span
				className={cn(
					"w-7 shrink-0 text-center text-sm font-semibold tabular-nums",
					entry.rank <= 3 && placed.length > 0
						? "text-amber-500"
						: "text-muted-foreground"
				)}>
				{entry.rank}
			</span>
			<AvatarPreviewDialog
				src={entry.userAvatar}
				alt={entry.userName}
				fallback={getInitials(entry.userName)}
				className="size-9 shrink-0"
				fallbackClassName="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white"
				verified={entry.userVerified}
			/>
			<div className="min-w-0 flex-1 space-y-1">
				<div className="flex min-w-0 items-center gap-1">
					<p className="truncate text-sm font-medium">{entry.userName}</p>
					{entry.userVerified && (
						<VerifiedBadge className="size-3.5 shrink-0" />
					)}
				</div>
				{placed.length > 0 ? (
					<div className="flex flex-wrap gap-1">
						{placed.map(({ position, count }) => (
							<span
								key={position}
								className={cn(
									"flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[11px] font-semibold",
									position === 1
										? "bg-amber-400 text-amber-950"
										: "bg-neutral-900/80 text-white dark:bg-neutral-100/90 dark:text-neutral-900"
								)}>
								{position === 1 && <Trophy className="size-3" />}
								{ORDINALS[position - 1]} × {count}
							</span>
						))}
					</div>
				) : (
					<p className="text-xs text-muted-foreground">No placements yet</p>
				)}
			</div>
			<span className="shrink-0 text-xs text-muted-foreground">
				{entry.photosCount} {entry.photosCount === 1 ? "photo" : "photos"}
			</span>
		</li>
	)
}
