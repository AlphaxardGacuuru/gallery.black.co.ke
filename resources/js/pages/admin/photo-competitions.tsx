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
import { Switch } from "@/components/ui/switch"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import {
	type AdminExtraSlotSettings,
	type AdminPhotoCompetitionSummary,
	type AdminPhotoCompetitionWinner,
	type AdminPhotoSlotPurchase,
	type PhotoCompetitionSchedule,
	useAdminPhotoCompetitions,
	useAdminPhotoSlotPurchases,
	useAdminRecentPhotoCompetitions,
	usePayCompetitionWinner,
	useUpdateActiveCompetition,
	useUpdateExtraSlot,
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
						{updateActive.isPending && (
							<Loader2 className="size-3.5 animate-spin" />
						)}
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
		<div className="w-full space-y-3 sm:max-w-xl sm:flex-1 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Weekly prize tiers"
				description="Applies going forward — position 1 pays first, positions with KES 0 aren't ranked."
			/>
			<div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
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
					{updatePrizeTiers.isPending && (
						<Loader2 className="size-3.5 animate-spin" />
					)}
					Save
				</Button>
			</div>
		</div>
	)
}

function ExtraSlotSettings({
	extraSlot,
}: {
	extraSlot: AdminExtraSlotSettings
}) {
	const updateExtraSlot = useUpdateExtraSlot()
	const [enabled, setEnabled] = useState(extraSlot.enabled)
	const [price, setPrice] = useState(String(extraSlot.price))

	function handleSave() {
		const parsedPrice = Number(price)

		if (!Number.isFinite(parsedPrice) || parsedPrice < 0) {
			toast.error("Enter a valid price")
			return
		}

		updateExtraSlot.mutate(
			{ enabled, price: parsedPrice },
			{
				onSuccess: () => toast.success("Extra slot settings updated"),
				onError: () => toast.error("Couldn't update the extra slot settings"),
			}
		)
	}

	return (
		<div className="w-full space-y-3 sm:max-w-sm sm:flex-1 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Buy an extra slot"
				description="Let entrants pay for a second submission via M-Pesa STK push."
			/>
			<div className="flex items-center justify-between gap-4">
				<p className="text-sm font-medium">Enabled</p>
				<Switch
					checked={enabled}
					onCheckedChange={setEnabled}
					aria-label="Toggle buy-an-extra-slot"
				/>
			</div>
			<Input
				type="number"
				min={0}
				label="Price (KES)"
				value={price}
				onChange={(event) => setPrice(event.target.value)}
			/>
			<div className="flex justify-end">
				<Button
					disabled={updateExtraSlot.isPending}
					onClick={handleSave}>
					{updateExtraSlot.isPending && (
						<Loader2 className="size-3.5 animate-spin" />
					)}
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
		<div className="w-full space-y-3 sm:w-auto sm:max-w-lg rounded-lg border p-4">
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
					{updateSchedule.isPending && (
						<Loader2 className="size-3.5 animate-spin" />
					)}
					Save
				</Button>
			</div>
		</div>
	)
}

function WinnerRow({ winner }: { winner: AdminPhotoCompetitionWinner }) {
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
			) : winner.userPhone ? (
				// Kopokopo's sendMoney() call takes the phone number/amount
				// directly (see KopokopoTransferService::payWinner()), it
				// never looks up a saved KopokopoRecipient, so paying doesn't
				// need one to exist first.
				<Button
					variant="outline"
					size="sm"
					disabled={payWinner.isPending}
					onClick={handlePay}>
					{payWinner.isPending && <Loader2 className="size-3.5 animate-spin" />}
					Pay
				</Button>
			) : (
				<span className="text-xs text-muted-foreground">No phone number</span>
			)}
		</div>
	)
}

function WinnersDialog({
	competition,
}: {
	competition: AdminPhotoCompetitionSummary
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
						/>
					))}
				</div>
			</DialogContent>
		</Dialog>
	)
}

function CompetitionStatusBadge({ status }: { status: string }) {
	return (
		<Badge
			variant="secondary"
			className={cn(
				"capitalize",
				status === "active" &&
					"border-transparent bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
			)}>
			{status}
		</Badge>
	)
}

const SLOT_PURCHASE_STATUS_CLASSES: Record<AdminPhotoSlotPurchase["status"], string> =
	{
		paid: "border-transparent bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
		pending: "border-transparent bg-amber-500/10 text-amber-600 dark:text-amber-400",
		failed: "border-transparent bg-red-500/10 text-red-600 dark:text-red-400",
	}

function SlotPurchaseStatusBadge({
	status,
}: {
	status: AdminPhotoSlotPurchase["status"]
}) {
	return (
		<Badge
			variant="secondary"
			className={cn("capitalize", SLOT_PURCHASE_STATUS_CLASSES[status])}>
			{status}
		</Badge>
	)
}

function SlotPurchasesTable() {
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(10)
	const { data } = useAdminPhotoSlotPurchases(page, perPage)

	const columns: ColumnDef<AdminPhotoSlotPurchase>[] = [
		{
			id: "buyer",
			header: "Buyer",
			cell: ({ row }) => (
				<div>
					<p className="font-medium">{row.original.userName ?? "—"}</p>
					{row.original.userPhone && (
						<p className="text-xs text-muted-foreground">
							{row.original.userPhone}
						</p>
					)}
				</div>
			),
		},
		{
			accessorKey: "competitionStartsAt",
			header: "Week",
			cell: ({ row }) =>
				row.original.competitionStartsAt
					? new Date(row.original.competitionStartsAt).toLocaleDateString()
					: "—",
		},
		{
			accessorKey: "amount",
			header: "Amount",
			cell: ({ row }) => `KES ${row.original.amount}`,
		},
		{
			accessorKey: "status",
			header: "Status",
			cell: ({ row }) => <SlotPurchaseStatusBadge status={row.original.status} />,
		},
		{
			accessorKey: "createdAt",
			header: "Purchased at",
			cell: ({ row }) => new Date(row.original.createdAt).toLocaleString(),
		},
		{
			accessorKey: "paidAt",
			header: "Paid at",
			cell: ({ row }) =>
				row.original.paidAt
					? new Date(row.original.paidAt).toLocaleString()
					: "—",
		},
	]

	return (
		<Card className="overflow-hidden">
			<CardHeader className="pb-4">
				<CardTitle>Extra slot purchases</CardTitle>
			</CardHeader>
			<CardContent>
				<DataTable
					columns={columns}
					data={data?.data ?? []}
					emptyMessage="No one has bought an extra slot yet"
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
	)
}

export default function AdminPhotoCompetitions() {
	const { data, isLoading } = useAdminPhotoCompetitions()
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(10)
	const { data: recent } = useAdminRecentPhotoCompetitions(page, perPage)

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
			cell: ({ row }) => <CompetitionStatusBadge status={row.original.status} />,
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
				<WinnersDialog competition={row.original} />
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
							<ExtraSlotSettings extraSlot={data.extraSlot} />
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

						<SlotPurchasesTable />
					</>
				)}
			</div>
		</>
	)
}
