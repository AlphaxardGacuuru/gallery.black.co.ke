import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import ReferFriends from "@/components/refer-friends"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Head } from "@/lib/spa"
import { type MyReferral, useMyReferrals } from "@/queries/referrals"
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

export default function Referrals() {
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data, isLoading } = useMyReferrals(page, perPage)

	return (
		<>
			<Head title="Referrals" />

			<h1 className="sr-only">Referrals</h1>

			<ReferFriends />

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
