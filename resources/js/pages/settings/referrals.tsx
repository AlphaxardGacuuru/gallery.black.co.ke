import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import ReferFriends from "@/components/refer-friends"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Skeleton } from "@/components/ui/skeleton"
import { Head } from "@/lib/spa"
import {
	type MyReferral,
	type ReferralEarnings,
	useMyReferrals,
	useReferralSettings,
} from "@/queries/referrals"
import { edit } from "@/routes/referrals"

const columns: ColumnDef<MyReferral>[] = [
	{
		accessorKey: "referredName",
		header: "Friend",
		cell: ({ row }) => row.original.referredName ?? "—",
	},
	{
		accessorKey: "createdAt",
		header: "Joined",
		cell: ({ row }) => new Date(row.original.createdAt).toLocaleDateString(),
	},
]

const formatKes = (amount: number) =>
	`KES ${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })}`

/** What the user's referrals have earned them, and how rewards are paid. */
function Earnings({ earnings }: { earnings?: ReferralEarnings }) {
	const { data: settings } = useReferralSettings()

	const figures = [
		{ label: "Paid to you", value: earnings?.paid },
		{ label: "On its way", value: earnings?.pending },
		{ label: "Owed to you", value: earnings?.owed },
	]

	return (
		<Card>
			<CardHeader className="pb-4">
				<CardTitle>Your earnings</CardTitle>
			</CardHeader>
			<CardContent className="space-y-4">
				<dl className="grid grid-cols-3 gap-4">
					{figures.map((figure) => (
						<div
							key={figure.label}
							className="flex flex-col-reverse">
							<dt className="text-sm text-muted-foreground">{figure.label}</dt>
							<dd className="text-xl font-semibold tracking-tight tabular-nums">
								{figure.value === undefined ? (
									<Skeleton className="h-7 w-20" />
								) : (
									formatKes(figure.value)
								)}
							</dd>
						</div>
					))}
				</dl>
				{settings && settings.threshold > 0 && (
					<p className="text-sm text-muted-foreground">
						You earn {formatKes(settings.rewardAmount)} for every{" "}
						{settings.threshold} friends who join
						{settings.photoBonusAmount > 0 &&
							`, plus ${formatKes(settings.photoBonusAmount)} when a friend posts their first photo`}
						. Rewards are sent to your M-Pesa once a full batch is reached, and
						"On its way" is money that's been sent but not confirmed yet.
					</p>
				)}
			</CardContent>
		</Card>
	)
}

export default function Referrals() {
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data, isLoading } = useMyReferrals(page, perPage)

	return (
		<>
			<Head title="Referrals" />

			<h1 className="sr-only">Referrals</h1>

			<ReferFriends />

			<Earnings earnings={data?.meta.earnings} />

			<Card className="overflow-hidden">
				<CardHeader className="pb-4">
					<CardTitle>Your referrals</CardTitle>
				</CardHeader>
				<CardContent>
					<DataTable
						columns={columns}
						data={data?.data ?? []}
						emptyMessage={
							isLoading
								? "Loading…"
								: "No referrals yet, share your link to get started."
						}
						pagination={{
							currentPage: data?.meta.current_page ?? 1,
							lastPage: data?.meta.last_page ?? 1,
							total: data?.meta.total ?? 0,
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
		</>
	)
}

Referrals.layout = {
	breadcrumbs: [
		{
			title: "Referrals",
			href: edit(),
		},
	],
}
