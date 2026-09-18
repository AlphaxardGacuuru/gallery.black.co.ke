import { Trophy, UserPlus, Users } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import Heading from "@/components/heading"
import AdminStatCard from "@/components/admin/AdminStatCard"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import {
	type AdminReferral,
	type AdminReferralLeaderboardEntry,
	useAdminReferrals,
	useAdminRecentReferrals,
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
]

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
			{entries.map((entry, index) => (
				<li
					key={entry.userId}
					className="flex items-center gap-3">
					<span className="w-5 shrink-0 text-center text-sm font-medium text-muted-foreground">
						{index + 1}
					</span>
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
				</li>
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
