import {
	Eye,
	Loader2,
	Megaphone,
	MousePointerClick,
	Pencil,
	Plus,
	Trash2,
	X,
} from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
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
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	type AdminNotice,
	type AdminNoticeAudience,
	type AdminNoticeEvent,
	type AdminNoticeInput,
	type AdminNoticeVariable,
	type AdminNoticesData,
	useAdminNoticeEvents,
	useAdminNotices,
	useDeleteNotice,
	useSaveNotice,
	useUpdateNoticeLimits,
} from "@/queries/admin"

const AUDIENCE_LABELS: Record<AdminNoticeAudience, string> = {
	all: "Everyone",
	never_referred: "Never referred anyone",
	not_entered_active_competition: "Not in this week's challenge",
}

const ACTION_LABELS: Record<AdminNoticeEvent["action"], string> = {
	shown: "Shown",
	primary_click: "Main button",
	secondary_click: "Second button",
	dismissed: "Closed",
}

const EMPTY_NOTICE: AdminNoticeInput = {
	key: "",
	title: "",
	body: "",
	icon: "megaphone",
	primaryLabel: "",
	primaryUrl: "",
	secondaryLabel: "Close",
	audience: "all",
	priority: 0,
	cooldownHours: 24,
	maxImpressions: null,
	stopAfterClick: true,
	isActive: true,
	startsAt: null,
	endsAt: null,
}

// <input type="datetime-local"> wants "YYYY-MM-DDTHH:mm" in local time.
function toDatetimeLocalValue(iso: string | null): string {
	if (!iso) {
		return ""
	}

	const date = new Date(iso)
	const pad = (value: number) => String(value).padStart(2, "0")

	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function percent(part: number, whole: number): string {
	return whole > 0 ? `${Math.round((part / whole) * 100)}%` : "-"
}

function errorMessage(error: unknown): string | undefined {
	const data = (
		error as {
			response?: {
				data?: { message?: string; errors?: Record<string, string[]> }
			}
		}
	).response?.data

	return Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message
}

function LimitsSettings({ limits }: { limits: AdminNoticesData["limits"] }) {
	const updateLimits = useUpdateNoticeLimits()
	const [dailyLimit, setDailyLimit] = useState(String(limits.dailyLimit))
	const [minGapHours, setMinGapHours] = useState(String(limits.minGapHours))

	function handleSave() {
		updateLimits.mutate(
			{ dailyLimit: Number(dailyLimit), minGapHours: Number(minGapHours) },
			{
				onSuccess: () => toast.success("Notice limits updated"),
				onError: (error) =>
					toast.error("Couldn't update the limits", {
						description: errorMessage(error),
					}),
			}
		)
	}

	return (
		<div className="max-w-3xl space-y-3 rounded-lg border p-4">
			<Heading
				variant="small"
				title="Limits for every user"
				description="These apply across all notices and always win. Set the daily limit to 0 to pause notices entirely."
			/>
			<div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
				<Input
					type="number"
					min={0}
					label="Notices per 24 hours"
					value={dailyLimit}
					onChange={(event) => setDailyLimit(event.target.value)}
				/>
				<Input
					type="number"
					min={0}
					label="Hours between notices"
					value={minGapHours}
					onChange={(event) => setMinGapHours(event.target.value)}
				/>
			</div>
			<div className="flex justify-end">
				<Button
					disabled={updateLimits.isPending}
					onClick={handleSave}>
					{updateLimits.isPending && (
						<Loader2 className="size-3.5 animate-spin" />
					)}
					Save
				</Button>
			</div>
		</div>
	)
}

function NoticeFormDialog({
	notice,
	options,
}: {
	notice?: AdminNotice
	options: AdminNoticesData
}) {
	const saveNotice = useSaveNotice()
	const [open, setOpen] = useState(false)
	const [form, setForm] = useState<AdminNoticeInput>(notice ?? EMPTY_NOTICE)

	function set<K extends keyof AdminNoticeInput>(
		key: K,
		value: AdminNoticeInput[K]
	) {
		setForm((current) => ({ ...current, [key]: value }))
	}

	function handleSave() {
		saveNotice.mutate(
			{
				...form,
				id: notice?.id,
				primaryUrl: form.primaryUrl || null,
				secondaryLabel: form.secondaryLabel || null,
			},
			{
				onSuccess: () => {
					toast.success(notice ? "Notice updated" : "Notice created")
					setOpen(false)
				},
				onError: (error) =>
					toast.error("Couldn't save the notice", {
						description: errorMessage(error),
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
					setForm(
						notice
							? {
									...notice,
									startsAt: toDatetimeLocalValue(notice.startsAt) || null,
									endsAt: toDatetimeLocalValue(notice.endsAt) || null,
								}
							: EMPTY_NOTICE
					)
				}
			}}>
			<DialogTrigger asChild>
				{notice ? (
					<Button
						variant="ghost"
						size="sm"
						aria-label={`Edit ${notice.key}`}>
						<Pencil className="size-3.5" />
					</Button>
				) : (
					<Button className="gap-1.5">
						<Plus className="size-4" />
						New notice
					</Button>
				)}
			</DialogTrigger>
			<DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
				<DialogHeader>
					<DialogTitle>{notice ? "Edit notice" : "New notice"}</DialogTitle>
				</DialogHeader>

				<div className="space-y-3">
					<div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
						<Input
							label="Key (for reports)"
							value={form.key}
							onChange={(event) => set("key", event.target.value)}
						/>
						<SelectField
							label="Icon"
							value={form.icon}
							onValueChange={(value) => set("icon", value)}>
							{options.icons.map((icon) => (
								<SelectItem
									key={icon}
									value={icon}>
									{icon}
								</SelectItem>
							))}
						</SelectField>
					</div>
					<Input
						label="Title"
						value={form.title}
						onChange={(event) => set("title", event.target.value)}
					/>
					<textarea
						className="min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm"
						placeholder="Body"
						value={form.body}
						onChange={(event) => set("body", event.target.value)}
					/>
					<VariablesHint variables={options.variables} />
					<div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
						<Input
							label="Main button"
							value={form.primaryLabel}
							onChange={(event) => set("primaryLabel", event.target.value)}
						/>
						<Input
							label="Main button link"
							placeholder="/settings/referrals"
							value={form.primaryUrl ?? ""}
							onChange={(event) => set("primaryUrl", event.target.value)}
						/>
						<Input
							label="Second button"
							value={form.secondaryLabel ?? ""}
							onChange={(event) => set("secondaryLabel", event.target.value)}
						/>
					</div>
					<div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
						<SelectField
							label="Who sees it"
							value={form.audience}
							onValueChange={(value) =>
								set("audience", value as AdminNoticeAudience)
							}>
							{options.audiences.map((audience) => (
								<SelectItem
									key={audience}
									value={audience}>
									{AUDIENCE_LABELS[audience]}
								</SelectItem>
							))}
						</SelectField>
						<Input
							type="number"
							min={0}
							label="Priority (higher shows first)"
							value={String(form.priority)}
							onChange={(event) => set("priority", Number(event.target.value))}
						/>
						<Input
							type="number"
							min={0}
							label="Hours before showing again"
							value={String(form.cooldownHours)}
							onChange={(event) =>
								set("cooldownHours", Number(event.target.value))
							}
						/>
						<Input
							type="number"
							min={1}
							label="Max times per user (blank = no cap)"
							value={
								form.maxImpressions === null ? "" : String(form.maxImpressions)
							}
							onChange={(event) =>
								set(
									"maxImpressions",
									event.target.value === "" ? null : Number(event.target.value)
								)
							}
						/>
						<Input
							type="datetime-local"
							label="Starts (optional)"
							value={form.startsAt ?? ""}
							onChange={(event) => set("startsAt", event.target.value || null)}
						/>
						<Input
							type="datetime-local"
							label="Ends (optional)"
							value={form.endsAt ?? ""}
							onChange={(event) => set("endsAt", event.target.value || null)}
						/>
					</div>
					<div className="flex items-center justify-between gap-4">
						<p className="text-sm font-medium">
							Stop showing once they click the main button
						</p>
						<Switch
							checked={form.stopAfterClick}
							onCheckedChange={(checked) => set("stopAfterClick", checked)}
							aria-label="Toggle stop after click"
						/>
					</div>
					<div className="flex items-center justify-between gap-4">
						<p className="text-sm font-medium">Active</p>
						<Switch
							checked={form.isActive}
							onCheckedChange={(checked) => set("isActive", checked)}
							aria-label="Toggle active"
						/>
					</div>
				</div>

				<DialogFooter>
					<Button
						disabled={saveNotice.isPending}
						onClick={handleSave}>
						{saveNotice.isPending && (
							<Loader2 className="size-3.5 animate-spin" />
						)}
						Save
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}

function VariablesHint({ variables }: { variables: AdminNoticeVariable[] }) {
	return (
		<div className="rounded-md bg-muted/50 p-3 text-xs text-muted-foreground">
			<p className="mb-1 font-medium text-foreground">
				Variables you can use in the title, body and buttons
			</p>
			<ul className="space-y-0.5">
				{variables.map((variable) => (
					<li key={variable.name}>
						<code className="text-foreground">{`{${variable.name}}`}</code>{" "}
						{variable.description}
						{variable.example !== null && ` (now: ${variable.example})`}
					</li>
				))}
			</ul>
			<p className="mt-1">
				If a variable has no value for someone, they won't see the notice.
			</p>
		</div>
	)
}

function DeleteNoticeButton({ notice }: { notice: AdminNotice }) {
	const deleteNotice = useDeleteNotice()

	function handleDelete() {
		if (
			!window.confirm(
				`Delete "${notice.key}"? Its click history is deleted too. Turning it off keeps the history.`
			)
		) {
			return
		}

		deleteNotice.mutate(notice.id, {
			onSuccess: () => toast.success("Notice deleted"),
			onError: () => toast.error("Couldn't delete the notice"),
		})
	}

	return (
		<Button
			variant="ghost"
			size="sm"
			aria-label={`Delete ${notice.key}`}
			disabled={deleteNotice.isPending}
			onClick={handleDelete}>
			<Trash2 className="size-3.5" />
		</Button>
	)
}

function noticeColumns(options: AdminNoticesData): ColumnDef<AdminNotice>[] {
	return [
		{
			accessorKey: "key",
			header: "Notice",
			cell: ({ row }) => (
				<div className="min-w-48">
					<p className="font-medium">{row.original.title}</p>
					<p className="text-xs text-muted-foreground">{row.original.key}</p>
				</div>
			),
		},
		{
			accessorKey: "isActive",
			header: "Status",
			cell: ({ row }) =>
				row.original.isActive ? (
					<Badge>Active</Badge>
				) : (
					<Badge variant="secondary">Off</Badge>
				),
		},
		{
			accessorKey: "audience",
			header: "Audience",
			cell: ({ row }) => AUDIENCE_LABELS[row.original.audience],
		},
		{ accessorKey: "priority", header: "Priority" },
		{
			id: "shown",
			header: "Shown",
			cell: ({ row }) =>
				`${row.original.stats.shown} (${row.original.stats.uniqueViewers} people)`,
		},
		{
			id: "primary",
			header: "Main button",
			cell: ({ row }) =>
				`${row.original.stats.primaryClicks} (${percent(row.original.stats.primaryClicks, row.original.stats.shown)})`,
		},
		{
			id: "secondary",
			header: "Second button",
			cell: ({ row }) => row.original.stats.secondaryClicks,
		},
		{
			id: "dismissed",
			header: "Closed",
			cell: ({ row }) => row.original.stats.dismissed,
		},
		{
			id: "actions",
			header: "",
			enableSorting: false,
			cell: ({ row }) => (
				<div className="flex justify-end">
					<NoticeFormDialog
						notice={row.original}
						options={options}
					/>
					<DeleteNoticeButton notice={row.original} />
				</div>
			),
		},
	]
}

const eventColumns: ColumnDef<AdminNoticeEvent>[] = [
	{
		id: "user",
		header: "User",
		cell: ({ row }) => (
			<div>
				<p className="font-medium">{row.original.userName ?? "-"}</p>
				{row.original.userPhone && (
					<p className="text-xs text-muted-foreground">
						{row.original.userPhone}
					</p>
				)}
			</div>
		),
	},
	{
		accessorKey: "noticeKey",
		header: "Notice",
		cell: ({ row }) => row.original.noticeKey ?? "-",
	},
	{
		accessorKey: "action",
		header: "Action",
		cell: ({ row }) => (
			<Badge
				variant={
					row.original.action === "primary_click" ? "default" : "outline"
				}>
				{ACTION_LABELS[row.original.action]}
			</Badge>
		),
	},
	{
		accessorKey: "version",
		header: "Version",
		cell: ({ row }) => (
			<code className="text-xs text-muted-foreground">
				{row.original.version.slice(0, 7)}
			</code>
		),
	},
	{
		accessorKey: "createdAt",
		header: "When",
		cell: ({ row }) => new Date(row.original.createdAt).toLocaleString(),
	},
]

function EventLog({ notices }: { notices: AdminNotice[] }) {
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const [noticeId, setNoticeId] = useState("")
	const [action, setAction] = useState("")
	const { data } = useAdminNoticeEvents(page, perPage, { noticeId, action })

	return (
		<Card className="overflow-hidden">
			<CardHeader className="pb-4">
				<CardTitle>Who did what</CardTitle>
			</CardHeader>
			<CardContent className="space-y-3">
				<div className="flex flex-wrap items-end gap-2">
					<SelectField
						label="Notice"
						containerClassName="w-56"
						value={noticeId}
						onValueChange={(value) => {
							setNoticeId(value)
							setPage(1)
						}}>
						{notices.map((notice) => (
							<SelectItem
								key={notice.id}
								value={notice.id}>
								{notice.key}
							</SelectItem>
						))}
					</SelectField>
					<SelectField
						label="Action"
						containerClassName="w-44"
						value={action}
						onValueChange={(value) => {
							setAction(value)
							setPage(1)
						}}>
						{Object.entries(ACTION_LABELS).map(([value, label]) => (
							<SelectItem
								key={value}
								value={value}>
								{label}
							</SelectItem>
						))}
					</SelectField>
					{(noticeId || action) && (
						<Button
							variant="ghost"
							size="sm"
							className="gap-1"
							onClick={() => {
								setNoticeId("")
								setAction("")
								setPage(1)
							}}>
							<X className="size-3.5" />
							Clear
						</Button>
					)}
				</div>
				<DataTable
					columns={eventColumns}
					data={data?.data ?? []}
					emptyMessage="No one has seen a notice yet"
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

export default function AdminNotices() {
	const { data, isLoading } = useAdminNotices()

	const totals = (data?.notices ?? []).reduce(
		(sum, notice) => ({
			active: sum.active + (notice.isActive ? 1 : 0),
			shown: sum.shown + notice.stats.shown,
			clicks: sum.clicks + notice.stats.primaryClicks,
		}),
		{ active: 0, shown: 0, clicks: 0 }
	)

	return (
		<>
			<Head title="Notices" />

			<div className="space-y-6">
				<div className="flex items-start justify-between gap-4">
					<Heading
						variant="small"
						title="Notices"
						description="Pop-ups shown to users in the app: ads, announcements and the referral push"
					/>
					{data && <NoticeFormDialog options={data} />}
				</div>

				{isLoading || !data ? (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
						{Array.from({ length: 3 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-20"
							/>
						))}
					</div>
				) : (
					<>
						<div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
							<AdminStatCard
								label="Active notices"
								value={totals.active}
								icon={Megaphone}
							/>
							<AdminStatCard
								label="Times shown"
								value={totals.shown}
								icon={Eye}
							/>
							<AdminStatCard
								label="Main button clicks"
								value={totals.clicks}
								icon={MousePointerClick}
								tone="success"
							/>
						</div>

						<LimitsSettings limits={data.limits} />

						<Card className="overflow-hidden">
							<CardHeader className="pb-4">
								<CardTitle>All notices</CardTitle>
							</CardHeader>
							<CardContent>
								<DataTable
									columns={noticeColumns(data)}
									data={data.notices}
									emptyMessage="No notices yet"
								/>
							</CardContent>
						</Card>

						<EventLog notices={data.notices} />
					</>
				)}
			</div>
		</>
	)
}
