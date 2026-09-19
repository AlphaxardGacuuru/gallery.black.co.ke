import { Loader2, Trophy, UserPlus, Users } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import Heading from "@/components/heading"
import AdminStatCard from "@/components/admin/AdminStatCard"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	type AdminReferral,
	type AdminReferralLeaderboardEntry,
	useAdminReferrals,
	useAdminRecentReferrals,
	usePayReferrer,
	useUpdateReferralSettings,
} from "@/queries/admin"

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

const referralColumns: ColumnDef<AdminReferral>[] = [
	{
		accessorKey: "referrerName",
		header: "Referred by",
		cell: ({ row }) => row.original.referrerName ?? "—",
	},
	{
		accessorKey: "referredName",
		header: "New user",
		cell: ({ row }) => row.original.referredName ?? "—",
	},
	{
		accessorKey: "createdAt",
		header: "Signed up",
		cell: ({ row }) => new Date(row.original.createdAt).toLocaleString(),
	},
	{
		id: "paid",
		header: "Reward paid",
		enableSorting: false,
		cell: ({ row }) =>
			row.original.paidAt ? (
				<span>
					KES {row.original.amountPaid} ·{" "}
					{new Date(row.original.paidAt).toLocaleDateString()}
				</span>
			) : (
				<span className="text-muted-foreground">Not yet</span>
			),
	},
]

function RewardSettings({
	threshold,
	rewardAmount,
}: {
	threshold: number
	rewardAmount: number
}) {
	const updateSettings = useUpdateReferralSettings()
	const [thresholdInput, setThresholdInput] = useState(String(threshold))
	const [rewardInput, setRewardInput] = useState(String(rewardAmount))

	function handleSave() {
		const parsedThreshold = Number(thresholdInput)
		const parsedReward = Number(rewardInput)

		if (!Number.isFinite(parsedThreshold) || parsedThreshold < 1) {
			toast.error("Enter a valid referral threshold")
			return
		}

		if (!Number.isFinite(parsedReward) || parsedReward < 0) {
			toast.error("Enter a valid reward amount")
			return
		}

		updateSettings.mutate(
			{ threshold: parsedThreshold, rewardAmount: parsedReward },
			{
				onSuccess: () => toast.success("Referral reward settings updated"),
				onError: () => toast.error("Couldn't update the reward settings"),
			}
		)
	}

	return (
		<div className="max-w-sm space-y-3 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Referral reward"
				description="Every full batch of referrals splits the reward evenly."
			/>
			<div className="flex gap-2">
				<Input
					type="number"
					min={1}
					label="Referrals per reward"
					value={thresholdInput}
					onChange={(event) => setThresholdInput(event.target.value)}
				/>
				<Input
					type="number"
					min={0}
					label="Reward amount (KES)"
					value={rewardInput}
					onChange={(event) => setRewardInput(event.target.value)}
				/>
			</div>
			<div className="flex justify-end">
				<Button
					disabled={updateSettings.isPending}
					onClick={handleSave}>
					Save
				</Button>
			</div>
		</div>
	)
}

function LeaderboardEntryRow({ entry }: { entry: AdminReferralLeaderboardEntry }) {
	const payReferrer = usePayReferrer()

	function handlePay() {
		payReferrer.mutate(entry.userId, {
			onSuccess: () => toast.success(`Reward sent to ${entry.name}`),
			onError: (error) =>
				toast.error("Couldn't pay this referrer", {
					description: error.message,
				}),
		})
	}

	return (
		<li className="flex items-center gap-3">
			<Avatar className="size-8 shrink-0">
				<AvatarImage
					src={entry.avatar ?? undefined}
					alt={entry.name}
				/>
				<AvatarFallback>{initials(entry.name)}</AvatarFallback>
			</Avatar>
			<span className="min-w-0 flex-1 truncate text-sm font-medium">
				{entry.name}
			</span>
			<span className="shrink-0 text-sm tabular-nums text-muted-foreground">
				{entry.referralsCount}{" "}
				{entry.referralsCount === 1 ? "referral" : "referrals"}
			</span>
			{entry.eligibleAmount > 0 && (
				<Button
					variant="outline"
					size="sm"
					disabled={payReferrer.isPending}
					onClick={handlePay}>
					{payReferrer.isPending && (
						<Loader2 className="size-3.5 animate-spin" />
					)}
					Pay KES {entry.eligibleAmount}
				</Button>
			)}
		</li>
	)
}

function Leaderboard({
	entries,
}: {
	entries: AdminReferralLeaderboardEntry[]
}) {
	if (entries.length === 0) {
		return (
			<p className="text-sm text-muted-foreground">
				No one has referred a friend yet.
			</p>
		)
	}

	return (
		<ol className="space-y-3">
			{entries.map((entry) => (
				<LeaderboardEntryRow
					key={entry.userId}
					entry={entry}
				/>
			))}
		</ol>
	)
}

export default function AdminReferrals() {
	const { data, isLoading } = useAdminReferrals()
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data: recent } = useAdminRecentReferrals(page, perPage)

	return (
		<>
			<Head title="Referrals" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Referrals"
					description="See who's bringing in new users, so you know who to reward"
				/>

				{isLoading || !data ? (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
						{Array.from({ length: 2 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-20"
							/>
						))}
					</div>
				) : (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
						<AdminStatCard
							label="Total referrals"
							value={data.totalReferrals}
							icon={UserPlus}
							tone="success"
						/>
						<AdminStatCard
							label="Users who've referred someone"
							value={data.totalReferrers}
							icon={Users}
						/>
					</div>
				)}

				{data && (
					<RewardSettings
						threshold={data.threshold}
						rewardAmount={data.rewardAmount}
					/>
				)}

				<Card>
					<CardHeader className="pb-4">
						<CardTitle className="flex items-center gap-2">
							<Trophy className="size-4" />
							Top referrers
						</CardTitle>
					</CardHeader>
					<CardContent>
						<Leaderboard entries={data?.leaderboard ?? []} />
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader className="pb-4">
						<CardTitle>All referrals</CardTitle>
					</CardHeader>
					<CardContent>
						<DataTable
							columns={referralColumns}
							data={recent?.data ?? []}
							emptyMessage="No referrals yet"
							pagination={{
								currentPage: recent?.meta.current_page ?? 1,
								lastPage: recent?.meta.last_page ?? 1,
								total: recent?.meta.total ?? 0,
								pageSize: perPage,
								onPageChange: setPage,
								onPageSizeChange: (size) => {
									setPerPage(size)
									setPage(1)
								},
							}}
						/>
					</CardContent>
				</Card>
			</div>
		</>
	)
}
