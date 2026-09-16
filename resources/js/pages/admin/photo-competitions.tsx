import { Images, Loader2, Pencil, ThumbsUp, Trophy } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { Head } from "@/lib/spa"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import {
	Dialog,
	DialogContent,
	DialogFooter,
	DialogHeader,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import toast from "@/lib/toast"
import {
	type AdminPhotoCompetitionSummary,
	type PhotoCompetitionSchedule,
	useAdminPhotoCompetitions,
	useAdminRecentPhotoCompetitions,
	usePayCompetitionWinner,
	useUpdateActiveCompetition,
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

// <input type="datetime-local"> wants "YYYY-MM-DDTHH:mm" in the viewer's
// local time zone, with no "Z"/offset suffix — neither Date.toISOString()
// (always UTC) nor the raw ISO string from the API can be used directly.
function toDatetimeLocalValue(iso: string): string {
	const date = new Date(iso)
	const pad = (value: number) => String(value).padStart(2, "0")

	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

type ActiveCompetition = {
	id: string
	endsAt: string
	prizeAmount: number
	photosCount: number
}

function EditActiveCompetitionModal({
	current,
}: {
	current: ActiveCompetition
}) {
	const updateActive = useUpdateActiveCompetition()
	const [open, setOpen] = useState(false)
	const [prizeAmount, setPrizeAmount] = useState(String(current.prizeAmount))
	const [endsAt, setEndsAt] = useState(toDatetimeLocalValue(current.endsAt))

	function handleSave() {
		const parsedPrizeAmount = Number(prizeAmount)

		if (!Number.isFinite(parsedPrizeAmount) || parsedPrizeAmount < 0) {
			toast.error("Enter a valid amount")
			return
		}

		if (!endsAt) {
			toast.error("Pick an end date and time")
			return
		}

		updateActive.mutate(
			{
				prizeAmount: parsedPrizeAmount,
				// Sent as-is (the input's own local wall-clock value, e.g.
				// "2026-09-20T18:30"), not converted to a UTC instant here —
				// this app stores/interprets naive datetimes as
				// config('app.timezone') throughout (see
				// PhotoCompetition::scheduledWindowFor()), so converting to
				// UTC in JS first would get reinterpreted as local again on
				// the way back, shifting it by the timezone offset.
				endsAt,
			},
			{
				onSuccess: () => {
					toast.success("Active competition updated")
					setOpen(false)
				},
				onError: () =>
					toast.error("Couldn't update the active competition", {
						description: "Make sure the end time is after it started.",
					}),
			}
		)
	}

	return (
		<Dialog
			open={open}
			onOpenChange={(next) => {
				setOpen(next)
				if (next) {
					setPrizeAmount(String(current.prizeAmount))
					setEndsAt(toDatetimeLocalValue(current.endsAt))
				}
			}}>
			<DialogTrigger asChild>
				<Button
					variant="outline"
					size="sm"
					className="gap-1.5">
					<Pencil className="size-3.5" />
					Edit
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>Edit active competition</DialogTitle>
				</DialogHeader>
				<div className="space-y-4">
					<Input
						type="number"
						min={0}
						label="Prize amount (KES)"
						value={prizeAmount}
						onChange={(event) => setPrizeAmount(event.target.value)}
					/>
					<Input
						type="datetime-local"
						label="Ends at"
						value={endsAt}
						onChange={(event) => setEndsAt(event.target.value)}
					/>
				</div>
				<DialogFooter>
					<Button
						disabled={updateActive.isPending}
						onClick={handleSave}>
						Save
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}

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
			<div className="flex items-start gap-2">
				<Input
					type="number"
					min={0}
					label="Prize amount (KES)"
					value={amount}
					onChange={(event) => setAmount(event.target.value)}
				/>
			</div>
			<div className="flex justify-end">
				<Button
					disabled={amount.trim() === "" || updatePrizeAmount.isPending}
					onClick={handleSave}>
					Save
				</Button>
			</div>
		</div>
	)
}

function ScheduleSettings({
	schedule,
}: {
	schedule: PhotoCompetitionSchedule
}) {
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

	return (
		<div className="max-w-lg space-y-3 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Weekly schedule"
				description="When future competitions automatically start and end."
			/>
			<div className="flex gap-2">
				<SelectField
					label="Start day"
					value={String(startDay)}
					onValueChange={(value) => setStartDay(Number(value))}>
					{WEEKDAYS.map((day, index) => (
						<SelectItem
							key={day}
							value={String(index)}>
							{day}
						</SelectItem>
					))}
				</SelectField>
				<Input
					type="time"
					label="Start time"
					value={startTime}
					onChange={(event) => setStartTime(event.target.value)}
				/>
			</div>
			<div className="flex gap-2">
				<SelectField
					label="End day"
					value={String(endDay)}
					onValueChange={(value) => setEndDay(Number(value))}>
					{WEEKDAYS.map((day, index) => (
						<SelectItem
							key={day}
							value={String(index)}>
							{day}
						</SelectItem>
					))}
				</SelectField>
				<Input
					type="time"
					label="End time"
					value={endTime}
					onChange={(event) => setEndTime(event.target.value)}
				/>
			</div>
			<div className="flex justify-end">
				<Button
					disabled={updateSchedule.isPending}
					onClick={handleSave}>
					Save
				</Button>
			</div>
		</div>
	)
}

function PayWinnerCell({
	competition,
}: {
	competition: AdminPhotoCompetitionSummary
}) {
	const payWinner = usePayCompetitionWinner()

	if (!competition.winnerName) {
		return <span className="text-muted-foreground">—</span>
	}

	if (competition.prizePaidAt) {
		return (
			<div className="flex items-center gap-2">
				<span>{competition.winnerName}</span>
				<Badge variant="secondary">Paid</Badge>
			</div>
		)
	}

	function handlePay() {
		payWinner.mutate(competition.id, {
			onSuccess: () =>
				toast.success(`Prize sent to ${competition.winnerName}`),
			onError: (error) =>
				toast.error("Couldn't pay the winner", {
					description: error.message,
				}),
		})
	}

	return (
		<div className="flex items-center gap-2">
			<span>{competition.winnerName}</span>
			<Button
				variant="outline"
				size="sm"
				disabled={payWinner.isPending}
				onClick={handlePay}>
				{payWinner.isPending && <Loader2 className="size-3.5 animate-spin" />}
				Pay KES {competition.prizeAmount}
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
		cell: ({ row }) => (
			<span className="capitalize">{row.original.status}</span>
		),
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
		cell: ({ row }) => <PayWinnerCell competition={row.original} />,
	},
]

export default function AdminPhotoCompetitions() {
	const { data, isLoading } = useAdminPhotoCompetitions()
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(10)
	const { data: recent } = useAdminRecentPhotoCompetitions(page, perPage)

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
							<div className="flex items-center justify-between gap-4 rounded-lg border p-4 text-sm">
								<div>
									<p className="font-medium">Active competition</p>
									<p className="mt-1 text-muted-foreground">
										{data.current.photosCount} entries so far · ends{" "}
										{new Date(data.current.endsAt).toLocaleString()} · KES{" "}
										{data.current.prizeAmount} prize
									</p>
								</div>
								<EditActiveCompetitionModal current={data.current} />
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
									data={recent?.data ?? []}
									emptyMessage="No competitions yet"
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
					</>
				)}
			</div>
		</>
	)
}
