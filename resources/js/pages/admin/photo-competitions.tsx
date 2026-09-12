import { Images, ThumbsUp, Trophy } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { Head } from "@/lib/spa"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Skeleton } from "@/components/ui/skeleton"
import toast from "@/lib/toast"
import {
	type AdminPhotoCompetitionSummary,
	type PhotoCompetitionSchedule,
	useAdminPhotoCompetitions,
	useUpdatePhotoCompetitionSchedule,
	useUpdatePrizeAmount,
} from "@/queries/admin"

const WEEKDAYS = [
	"Sunday",
	"Monday",
	"Tuesday",
	"Wednesday",
	"Thursday",
	"Friday",
	"Saturday",
]

function PrizeAmountSettings({ prizeAmount }: { prizeAmount: number }) {
	const updatePrizeAmount = useUpdatePrizeAmount()
	const [amount, setAmount] = useState(String(prizeAmount))

	function handleSave() {
		const parsed = Number(amount)

		if (!Number.isFinite(parsed) || parsed < 0) {
			toast.error("Enter a valid amount")
			return
		}

		updatePrizeAmount.mutate(parsed, {
			onSuccess: () => toast.success("Prize amount updated"),
			onError: () => toast.error("Couldn't update the prize amount"),
		})
	}

	return (
		<div className="max-w-sm flex-1 space-y-2 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Weekly prize amount"
				description="Applies to the next competition the scheduler starts."
			/>
			<div className="flex gap-2">
				<input
					type="number"
					min={0}
					value={amount}
					onChange={(event) => setAmount(event.target.value)}
					className="w-full rounded-md border bg-background px-3 py-2 text-sm"
				/>
				<Button
					disabled={amount.trim() === "" || updatePrizeAmount.isPending}
					onClick={handleSave}>
					Save
				</Button>
			</div>
		</div>
	)
}

function ScheduleSettings({ schedule }: { schedule: PhotoCompetitionSchedule }) {
	const updateSchedule = useUpdatePhotoCompetitionSchedule()
	const [startDay, setStartDay] = useState(schedule.startDay)
	const [startTime, setStartTime] = useState(schedule.startTime)
	const [endDay, setEndDay] = useState(schedule.endDay)
	const [endTime, setEndTime] = useState(schedule.endTime)

	function handleSave() {
		updateSchedule.mutate(
			{ startDay, startTime, endDay, endTime },
			{
				onSuccess: () => toast.success("Schedule updated"),
				onError: () =>
					toast.error("Couldn't update the schedule", {
						description: "Make sure the challenge ends after it starts.",
					}),
			}
		)
	}

	const selectClassName = "rounded-md border bg-background px-3 py-2 text-sm"

	return (
		<div className="max-w-sm space-y-3 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Weekly schedule"
				description="When future competitions automatically start and end."
			/>
			<div className="space-y-1">
				<p className="text-xs font-medium text-muted-foreground">Starts</p>
				<div className="flex gap-2">
					<select
						value={startDay}
						onChange={(event) => setStartDay(Number(event.target.value))}
						className={selectClassName}>
						{WEEKDAYS.map((day, index) => (
							<option
								key={day}
								value={index}>
								{day}
							</option>
						))}
					</select>
					<input
						type="time"
						value={startTime}
						onChange={(event) => setStartTime(event.target.value)}
						className={selectClassName}
					/>
				</div>
			</div>
			<div className="space-y-1">
				<p className="text-xs font-medium text-muted-foreground">Ends</p>
				<div className="flex gap-2">
					<select
						value={endDay}
						onChange={(event) => setEndDay(Number(event.target.value))}
						className={selectClassName}>
						{WEEKDAYS.map((day, index) => (
							<option
								key={day}
								value={index}>
								{day}
							</option>
						))}
					</select>
					<input
						type="time"
						value={endTime}
						onChange={(event) => setEndTime(event.target.value)}
						className={selectClassName}
					/>
				</div>
			</div>
			<Button
				disabled={updateSchedule.isPending}
				onClick={handleSave}>
				Save
			</Button>
		</div>
	)
}

const competitionColumns: ColumnDef<AdminPhotoCompetitionSummary>[] = [
	{
		accessorKey: "startsAt",
		header: "Starts at",
		cell: ({ row }) => new Date(row.original.startsAt).toLocaleString(),
	},
	{
		accessorKey: "endsAt",
		header: "Ends at",
		cell: ({ row }) => new Date(row.original.endsAt).toLocaleString(),
	},
	{
		accessorKey: "status",
		header: "Status",
		cell: ({ row }) => <span className="capitalize">{row.original.status}</span>,
	},
	{
		accessorKey: "photosCount",
		header: "Entries",
	},
	{
		accessorKey: "prizeAmount",
		header: "Prize",
		cell: ({ row }) => `KES ${row.original.prizeAmount}`,
	},
	{
		id: "winner",
		header: "Winner",
		enableSorting: false,
		cell: ({ row }) => row.original.winnerName ?? "—",
	},
]

export default function AdminPhotoCompetitions() {
	const { data, isLoading } = useAdminPhotoCompetitions()

	return (
		<>
			<Head title="Photo challenge" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Photo challenge"
					description="Weekly competition stats and settings"
				/>

				{isLoading || !data ? (
					<div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
						{Array.from({ length: 3 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-20"
							/>
						))}
					</div>
				) : (
					<>
						<div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
							<AdminStatCard
								label="Competitions run"
								value={data.totals.totalCompetitions}
								icon={Trophy}
							/>
							<AdminStatCard
								label="Photos submitted"
								value={data.totals.totalPhotos}
								icon={Images}
								tone="success"
							/>
							<AdminStatCard
								label="Total likes"
								value={data.totals.totalLikes}
								icon={ThumbsUp}
							/>
						</div>

						{data.current && (
							<div className="rounded-lg border p-4 text-sm">
								<p className="font-medium">Active competition</p>
								<p className="mt-1 text-muted-foreground">
									{data.current.photosCount} entries so far · ends{" "}
									{new Date(data.current.endsAt).toLocaleString()} · KES{" "}
									{data.current.prizeAmount} prize
								</p>
							</div>
						)}

						<div className="flex flex-wrap gap-4">
							<PrizeAmountSettings prizeAmount={data.prizeAmount} />
							<ScheduleSettings schedule={data.schedule} />
						</div>

						<Card className="overflow-hidden">
							<CardHeader className="pb-4">
								<CardTitle>Recent competitions</CardTitle>
							</CardHeader>
							<CardContent>
								<DataTable
									columns={competitionColumns}
									data={data.recentCompetitions}
									emptyMessage="No competitions yet"
								/>
							</CardContent>
						</Card>
					</>
				)}
			</div>
		</>
	)
}
