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
import { Link } from "@/components/ui/link"
import { SelectField, SelectItem } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { normalizePhoneNumber } from "@/lib/phone"
import toast from "@/lib/toast"
import {
	type AdminPhotoCompetitionSummary,
	type AdminPhotoCompetitionWinner,
	type PhotoCompetitionSchedule,
	useAdminKopokopoRecipients,
	useAdminPhotoCompetitions,
	useAdminRecentPhotoCompetitions,
	usePayCompetitionWinner,
	useUpdateActiveCompetition,
	useUpdatePhotoCompetitionSchedule,
	useUpdatePrizeTiers,
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

const POSITION_LABELS = [
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
	photosCount: number
}

function EditActiveCompetitionModal({
	current,
}: {
	current: ActiveCompetition
}) {
	const updateActive = useUpdateActiveCompetition()
	const [open, setOpen] = useState(false)
	const [endsAt, setEndsAt] = useState(toDatetimeLocalValue(current.endsAt))

	function handleSave() {
		if (!endsAt) {
			toast.error("Pick an end date and time")
			return
		}

		updateActive.mutate(
			{
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

function PrizeTiersSettings({ prizeTiers }: { prizeTiers: number[] }) {
	const updatePrizeTiers = useUpdatePrizeTiers()
	const [tiers, setTiers] = useState(prizeTiers.map(String))

	function handleSave() {
		const parsed = tiers.map(Number)

		if (parsed.some((value) => !Number.isFinite(value) || value < 0)) {
			toast.error("Enter valid amounts")
			return
		}

		updatePrizeTiers.mutate(parsed, {
			onSuccess: () => toast.success("Prize tiers updated"),
			onError: () => toast.error("Couldn't update the prize tiers"),
		})
	}

	return (
		<div className="max-w-xl flex-1 space-y-3 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Weekly prize tiers"
				description="Applies going forward — position 1 pays first, positions with KES 0 aren't ranked."
			/>
			<div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
				{POSITION_LABELS.map((label, index) => (
					<Input
						key={label}
						type="number"
						min={0}
						label={label}
						value={tiers[index]}
						onChange={(event) => {
							const next = [...tiers]
							next[index] = event.target.value
							setTiers(next)
						}}
					/>
				))}
			</div>
			<div className="flex justify-end">
				<Button
					disabled={updatePrizeTiers.isPending}
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

function WinnerRow({
	winner,
	isRecipient,
}: {
	winner: AdminPhotoCompetitionWinner
	isRecipient: boolean
}) {
	const payWinner = usePayCompetitionWinner()

	function handlePay() {
		payWinner.mutate(winner.id, {
			onSuccess: () => toast.success(`Prize sent to ${winner.userName}`),
			onError: (error) =>
				toast.error("Couldn't pay the winner", {
					description: error.message,
				}),
		})
	}

	return (
		<div className="flex items-center justify-between gap-3 border-b py-2 last:border-b-0">
			<div className="flex items-center gap-2">
				<Badge
					variant="secondary"
					className="tabular-nums">
					#{winner.position}
				</Badge>
				<div>
					<p className="text-sm font-medium">{winner.userName ?? "—"}</p>
					<p className="text-xs text-muted-foreground">
						KES {winner.prizeAmount}
					</p>
				</div>
			</div>
			{winner.prizePaidAt ? (
				<Badge variant="secondary">Paid</Badge>
			) : isRecipient ? (
				<Button
					variant="outline"
					size="sm"
					disabled={payWinner.isPending}
					onClick={handlePay}>
					{payWinner.isPending && <Loader2 className="size-3.5 animate-spin" />}
					Pay
				</Button>
			) : (
				<Link
					href="/admin/users"
					variant="outline"
					size="sm">
					Create Recipient
				</Link>
			)}
		</div>
	)
}

function WinnersDialog({
	competition,
	registeredPhones,
}: {
	competition: AdminPhotoCompetitionSummary
	registeredPhones: Set<string>
}) {
	if (competition.winners.length === 0) {
		return <span className="text-muted-foreground">—</span>
	}

	const paidCount = competition.winners.filter(
		(winner) => winner.prizePaidAt
	).length

	return (
		<Dialog>
			<DialogTrigger asChild>
				<Button
					variant="outline"
					size="sm">
					{paidCount}/{competition.winners.length} paid
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>Winners</DialogTitle>
				</DialogHeader>
				<div className="space-y-1">
					{competition.winners.map((winner) => (
						<WinnerRow
							key={winner.id}
							winner={winner}
							isRecipient={
								!!winner.userPhone &&
								registeredPhones.has(normalizePhoneNumber(winner.userPhone))
							}
						/>
					))}
				</div>
			</DialogContent>
		</Dialog>
	)
}

export default function AdminPhotoCompetitions() {
	const { data, isLoading } = useAdminPhotoCompetitions()
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(10)
	const { data: recent } = useAdminRecentPhotoCompetitions(page, perPage)
	const { data: recipients } = useAdminKopokopoRecipients()

	const registeredPhones = new Set(
		(recipients ?? [])
			.filter(
				(recipient) =>
					recipient.type === "mobile_wallet" && recipient.phoneNumber
			)
			.map((recipient) => recipient.phoneNumber!)
	)

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
			id: "winners",
			header: "Winners",
			enableSorting: false,
			cell: ({ row }) => (
				<WinnersDialog
					competition={row.original}
					registeredPhones={registeredPhones}
				/>
			),
		},
	]

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
										{new Date(data.current.endsAt).toLocaleString()} · top prize
										KES {data.prizeTiers[0]}
									</p>
								</div>
								<EditActiveCompetitionModal current={data.current} />
							</div>
						)}

						<div className="flex flex-wrap gap-4">
							<PrizeTiersSettings prizeTiers={data.prizeTiers} />
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
