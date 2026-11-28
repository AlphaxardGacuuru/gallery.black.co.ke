import {
	Bookmark,
	Grid3x3,
	Heart,
	Image as ImageIcon,
	type LucideIcon,
	Trophy,
	Wallet,
} from "lucide-react"
import { useState } from "react"
import { Head } from "@/lib/spa"
import { PhotoGrid } from "@/components/photos/PhotoGrid"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import VerifiedBadge, { VerifiedAvatar } from "@/components/verified-badge"
import { useApp } from "@/contexts/AppContext"
import { useInitials } from "@/hooks/use-initials"
import { cn } from "@/lib/utils"
import {
	useBookmarkedPhotos,
	useUserPhotos,
	useUserProfile,
} from "@/queries/photos"
import { edit } from "@/routes/profile"

type Tab = "posts" | "saved"

/**
 * A user's profile: who they are, how they've done in the challenges and a
 * grid of their posts. On your own profile a Saved tab lists the photos
 * you bookmarked.
 */
export function UserProfile({ userId }: { userId: string }) {
	const { auth } = useApp()
	const isOwn = auth !== undefined && String(auth.id) === userId
	const [tab, setTab] = useState<Tab>("posts")
	const { data: profile, isLoading } = useUserProfile(userId)
	const posts = useUserPhotos(userId)
	const saved = useBookmarkedPhotos(isOwn && tab === "saved")
	const getInitials = useInitials()

	const tabs: { value: Tab; label: string; icon: LucideIcon }[] = [
		{ value: "posts", label: "Posts", icon: Grid3x3 },
		...(isOwn
			? [{ value: "saved" as const, label: "Saved", icon: Bookmark }]
			: []),
	]

	return (
		<>
			<Head title={profile?.name ?? "Profile"} />

			<div className="space-y-6">
				{isLoading || !profile ? (
					<div className="space-y-4">
						<div className="flex items-center gap-4">
							<Skeleton className="size-20 shrink-0 rounded-full" />
							<div className="space-y-2">
								<Skeleton className="h-6 w-40" />
								<Skeleton className="h-4 w-24" />
							</div>
						</div>
						<Skeleton className="h-20 w-full sm:h-10" />
					</div>
				) : (
					<header className="space-y-4">
						<div className="flex items-center gap-4">
							<VerifiedAvatar
								verified={profile.verified}
								badgeClassName="size-5">
								<Avatar className="size-20">
									<AvatarImage
										src={profile.avatar ?? undefined}
										alt={profile.name}
									/>
									<AvatarFallback className="text-xl">
										{getInitials(profile.name)}
									</AvatarFallback>
								</Avatar>
							</VerifiedAvatar>
							<div className="min-w-0 space-y-0.5">
								<div className="flex min-w-0 items-center gap-1.5">
									<h2 className="truncate text-xl font-semibold tracking-tight">
										{profile.name}
									</h2>
									{profile.verified && (
										<VerifiedBadge className="size-4 shrink-0" />
									)}
								</div>
								<p className="text-xs text-muted-foreground">
									Joined{" "}
									{new Date(profile.joinedAt).toLocaleDateString(undefined, {
										day: "numeric",
										month: "long",
										year: "numeric",
									})}
								</p>
							</div>
						</div>
						<dl className="grid grid-cols-4 gap-2 sm:gap-4">
							<Stat
								label="Posts"
								value={formatCount(profile.stats.posts)}
								icon={ImageIcon}
							/>
							<Stat
								label="Likes"
								value={formatCount(profile.stats.likes)}
								icon={Heart}
							/>
							<Stat
								label="KES won"
								value={formatCount(profile.stats.cashWon)}
								icon={Wallet}
							/>
							<Stat
								label="Rank"
								value={
									profile.stats.leaderboardPosition
										? `#${profile.stats.leaderboardPosition}`
										: "-"
								}
								icon={Trophy}
							/>
						</dl>
						{isOwn && (
							<Link
								href={edit().url}
								variant="outline"
								size="sm"
								className="w-full">
								Edit profile
							</Link>
						)}
					</header>
				)}

				<div className="space-y-4">
					<div
						role="tablist"
						className="flex border-b">
						{tabs.map(({ value, label, icon: Icon }) => (
							<button
								key={value}
								type="button"
								role="tab"
								aria-selected={tab === value}
								onClick={() => setTab(value)}
								className={cn(
									"-mb-px flex flex-1 cursor-pointer items-center justify-center gap-2 border-b-2 py-3 text-sm font-medium transition-colors",
									tab === value
										? "border-foreground text-foreground"
										: "border-transparent text-muted-foreground hover:text-foreground"
								)}>
								<Icon className="size-4" />
								{label}
							</button>
						))}
					</div>

					{tab === "saved" && isOwn ? (
						<PhotoGrid
							query={saved}
							empty={
								<EmptyGrid
									icon={Bookmark}
									title="No saved photos yet"
									text="Tap the bookmark on any photo to save it here."
								/>
							}
						/>
					) : (
						<PhotoGrid
							query={posts}
							empty={
								<EmptyGrid
									icon={ImageIcon}
									title="No posts yet"
									text={
										isOwn
											? "Photos you enter into a challenge show up here."
											: "Their challenge entries will show up here."
									}
								/>
							}
						/>
					)}
				</div>
			</div>
		</>
	)
}

// Shortens big numbers the way Instagram does (1,234 to 1.2K) so all four
// stats fit on one line on a phone.
function formatCount(value: number): string {
	return new Intl.NumberFormat(undefined, {
		notation: "compact",
		maximumFractionDigits: 1,
	}).format(value)
}

function Stat({
	label,
	value,
	icon: Icon,
}: {
	label: string
	value: string
	icon: LucideIcon
}) {
	return (
		// On phones the icon sits above the number so all four stats fit on one
		// line, and beside it from tablet width up.
		<div className="flex min-w-0 flex-col items-center gap-1.5 text-center sm:flex-row sm:gap-3 sm:text-left">
			<div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted sm:size-9">
				<Icon className="size-4" />
			</div>
			{/* Label first for screen readers, shown under the number. */}
			<div className="flex w-full min-w-0 flex-col-reverse">
				<dt className="truncate text-xs text-muted-foreground">{label}</dt>
				<dd className="truncate font-semibold tabular-nums">{value}</dd>
			</div>
		</div>
	)
}

function EmptyGrid({
	icon: Icon,
	title,
	text,
}: {
	icon: LucideIcon
	title: string
	text: string
}) {
	return (
		<div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center text-muted-foreground">
			<Icon className="size-8" />
			<p className="font-medium">{title}</p>
			<p className="text-sm">{text}</p>
		</div>
	)
}
