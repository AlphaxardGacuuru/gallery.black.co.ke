import { isAxiosError } from "axios"
import { Check, Loader2, Users } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import { AvatarPreviewDialog } from "@/components/avatar-preview-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import VerifiedBadge from "@/components/verified-badge"
import { normalizePhoneNumber } from "@/lib/phone"
import { cn } from "@/lib/utils"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	type AdminUser,
	useAddKopokopoRecipient,
	useAdminDashboard,
	useAdminKopokopoRecipients,
	useAdminUsers,
	useAttachReferrer,
	useFlagUser,
	useRequestUserPhone,
	useToggleUserVerified,
} from "@/queries/admin"

type UserFilterKey =
	| "verified"
	| "hasPhone"
	| "seed"
	| "installed"
	| "referred"
	| "flagged"
type UserFilterValue = "any" | "yes" | "no"

const USER_FILTERS: {
	key: UserFilterKey
	label: string
	yes: string
	no: string
}[] = [
	{ key: "verified", label: "Verified", yes: "Verified", no: "Not verified" },
	{ key: "hasPhone", label: "Phone", yes: "Has phone", no: "No phone" },
	{ key: "seed", label: "Account", yes: "Seed accounts", no: "Real users" },
	{ key: "installed", label: "App", yes: "Installed", no: "Not installed" },
	{ key: "referred", label: "Referral", yes: "Referred", no: "Not referred" },
	{ key: "flagged", label: "Flagged", yes: "Flagged", no: "Not flagged" },
]

const DEFAULT_FILTERS: Record<UserFilterKey, UserFilterValue> = {
	verified: "any",
	hasPhone: "any",
	seed: "any",
	installed: "any",
	referred: "any",
	flagged: "any",
}

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

function nameParts(name: string): { firstName: string; lastName?: string } {
	const [firstName, ...rest] = name.trim().split(/\s+/)
	return { firstName, lastName: rest.join(" ") || undefined }
}

// Matches email-notification-settings.tsx's own labels, so this reads the
// same way an admin would see it on the user's own settings page. Email
// categories are opt-out (default on when the settings key is absent),
// see EmailNotificationCategory/User::wantsEmail().
const NOTIFICATION_LABELS: {
	key:
		| "competitionStartedNotification"
		| "competitionWonNotification"
		| "referralSignupNotification"
		| "photoLikedNotification"
	label: string
}[] = [
	{ key: "competitionStartedNotification", label: "Challenge announcements" },
	{ key: "competitionWonNotification", label: "Challenge results" },
	{ key: "referralSignupNotification", label: "Referral signups" },
	{ key: "photoLikedNotification", label: "Photo likes" },
]

function NotificationsCell({ user }: { user: AdminUser }) {
	const enabled = [
		...(user.pushSubscriptionsCount > 0 ? ["Push"] : []),
		...NOTIFICATION_LABELS.filter(
			({ key }) => (user.settings?.[key] ?? true) === true
		).map(({ label }) => label),
	]

	if (enabled.length === 0) {
		return <span className="text-xs text-muted-foreground">None</span>
	}

	return (
		<div className="flex flex-wrap gap-1">
			{enabled.map((label) => (
				<Badge
					key={label}
					variant="outline"
					className="whitespace-nowrap text-xs font-normal">
					{label}
				</Badge>
			))}
		</div>
	)
}

function KopokopoRecipientCell({
	user,
	isRegistered,
}: {
	user: AdminUser
	isRegistered: boolean
}) {
	const addRecipient = useAddKopokopoRecipient()
	const requestPhone = useRequestUserPhone()

	if (isRegistered) {
		return <Badge variant="default">Recipient</Badge>
	}

	if (!user.phone) {
		return (
			<Button
				variant="outline"
				size="sm"
				disabled={requestPhone.isPending}
				onClick={() =>
					requestPhone.mutate(user.id, {
						onSuccess: () =>
							toast.success(`Asked ${user.name} to add their phone number`),
						onError: (error) =>
							toast.error("Couldn't send the request", {
								description: isAxiosError<{ message?: string }>(error)
									? error.response?.data?.message
									: undefined,
							}),
					})
				}>
				{requestPhone.isPending && (
					<Loader2 className="size-3.5 animate-spin" />
				)}
				Request phone
			</Button>
		)
	}

	function handleClick() {
		const { firstName, lastName } = nameParts(user.name)

		addRecipient.mutate(
			{
				type: "mobile_wallet",
				description: `${user.name} — added from Users page`,
				firstName,
				lastName,
				phoneNumber: user.phone!,
			},
			{
				onSuccess: () =>
					toast.success(`${user.name} registered as a recipient`),
				onError: (error) =>
					toast.error("Couldn't register this recipient", {
						description: error.message,
					}),
			}
		)
	}

	return (
		<Button
			variant="outline"
			size="sm"
			disabled={addRecipient.isPending}
			onClick={handleClick}>
			{addRecipient.isPending && <Loader2 className="size-3.5 animate-spin" />}
			Register
		</Button>
	)
}

/**
 * Flag a user for suspected abuse (their likes stop counting, they can't
 * win or be paid, and they're notified on every channel), or lift it.
 * Flagging asks for confirmation and an admin-only reason.
 */
function FlagCell({ user }: { user: AdminUser }) {
	const [open, setOpen] = useState(false)
	const [reason, setReason] = useState("")
	const flagUser = useFlagUser()

	function submit(flagged: boolean) {
		flagUser.mutate(
			{ userId: user.id, flagged, reason: reason || undefined },
			{
				onSuccess: () => {
					toast.success(
						flagged ? `${user.name} flagged` : `${user.name} unflagged`
					)
					setOpen(false)
					setReason("")
				},
				onError: () => toast.error("Couldn't update this user"),
			}
		)
	}

	if (user.flaggedAt) {
		return (
			<div className="flex flex-wrap items-center gap-1.5">
				<Badge
					variant="destructive"
					title={user.flagReason ?? undefined}>
					Flagged
				</Badge>
				{user.flagRefutedAt && (
					<Badge
						variant="outline"
						title="Updated their phone number to dispute the flag">
						Refuted
					</Badge>
				)}
				<Button
					variant="outline"
					size="sm"
					disabled={flagUser.isPending}
					onClick={() => submit(false)}>
					Unflag
				</Button>
			</div>
		)
	}

	return (
		<Dialog
			open={open}
			onOpenChange={setOpen}>
			<DialogTrigger asChild>
				<Button
					variant="outline"
					size="sm">
					Flag
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogTitle>Flag {user.name}?</DialogTitle>
				<DialogDescription>
					Their likes stop counting in the running challenge, and they can't
					like, win or be paid until you unflag them. They'll get an email, push
					and in-app notification asking them to update their phone number if
					it's a mistake.
				</DialogDescription>

				{/* Reason Start */}
				<Input
					label="Reason (only admins see this)"
					value={reason}
					onChange={(event) => setReason(event.target.value)}
					maxLength={255}
				/>
				{/* Reason End */}

				<DialogFooter className="gap-2">
					<DialogClose asChild>
						<Button variant="secondary">Cancel</Button>
					</DialogClose>
					<Button
						variant="destructive"
						disabled={flagUser.isPending}
						onClick={() => submit(true)}>
						{flagUser.isPending && (
							<Loader2 className="size-3.5 animate-spin" />
						)}
						Flag user
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}

/**
 * "Referred by" cell: the referrer's name, or, for someone who signed up
 * without a referral link, a dialog to search for and credit a referrer.
 * A referral can't be changed once set (same as the signup flow).
 */
function ReferredByCell({ user }: { user: AdminUser }) {
	const [open, setOpen] = useState(false)
	const [search, setSearch] = useState("")
	const [referrer, setReferrer] = useState<AdminUser | null>(null)
	const { data, isFetching } = useAdminUsers({ name: search }, 1, 8, open)
	const attachReferrer = useAttachReferrer()

	if (user.referredBy) {
		return <span>{user.referredBy.name}</span>
	}

	const candidates = (data?.data ?? []).filter(
		(candidate) => candidate.id !== user.id
	)

	function handleOpenChange(nextOpen: boolean) {
		setOpen(nextOpen)

		if (!nextOpen) {
			setSearch("")
			setReferrer(null)
		}
	}

	function handleSave() {
		if (!referrer) return

		attachReferrer.mutate(
			{ userId: user.id, referrerId: referrer.id },
			{
				onSuccess: () => {
					toast.success(`${user.name} credited to ${referrer.name}`)
					handleOpenChange(false)
				},
				onError: (error) =>
					toast.error("Couldn't set the referrer", {
						description: isAxiosError<{ message?: string }>(error)
							? error.response?.data?.message
							: undefined,
					}),
			}
		)
	}

	return (
		<Dialog
			open={open}
			onOpenChange={handleOpenChange}>
			<DialogTrigger asChild>
				<Button
					variant="outline"
					size="sm">
					Set referrer
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogTitle>Who referred {user.name}?</DialogTitle>
				<DialogDescription>
					Use this for someone who signed up without a referral link. Once set,
					the referral counts toward the referrer's rewards and can't be changed
					here.
				</DialogDescription>

				<Input
					label="Search by name"
					value={search}
					onChange={(event) => setSearch(event.target.value)}
					autoFocus
				/>

				<div className="max-h-64 space-y-1 overflow-y-auto">
					{candidates.length === 0 ? (
						<p className="py-4 text-center text-sm text-muted-foreground">
							{isFetching ? "Searching…" : "No users found"}
						</p>
					) : (
						candidates.map((candidate) => (
							<button
								key={candidate.id}
								type="button"
								onClick={() => setReferrer(candidate)}
								className={cn(
									"flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent",
									referrer?.id === candidate.id && "bg-accent"
								)}>
								<span className="min-w-0 flex-1 truncate">
									<span className="font-medium">{candidate.name}</span>{" "}
									<span className="text-muted-foreground">
										{candidate.email}
									</span>
								</span>
								{referrer?.id === candidate.id && (
									<Check className="size-4 shrink-0" />
								)}
							</button>
						))
					)}
				</div>

				<DialogFooter className="gap-2">
					<DialogClose asChild>
						<Button variant="secondary">Cancel</Button>
					</DialogClose>
					<Button
						disabled={!referrer || attachReferrer.isPending}
						onClick={handleSave}>
						{attachReferrer.isPending && (
							<Loader2 className="size-3.5 animate-spin" />
						)}
						Save
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}

export default function AdminUsers() {
	const [search, setSearch] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const [filters, setFilters] =
		useState<Record<UserFilterKey, UserFilterValue>>(DEFAULT_FILTERS)
	const { data, isLoading } = useAdminUsers(
		{
			search,
			...(Object.fromEntries(
				Object.entries(filters)
					.filter(([, value]) => value !== "any")
					.map(([key, value]) => [key, value === "yes"])
			) as Partial<Record<UserFilterKey, boolean>>),
		},
		page,
		perPage
	)
	const hasActiveFilters =
		search !== "" || Object.values(filters).some((value) => value !== "any")

	function resetFilters() {
		setSearch("")
		setFilters(DEFAULT_FILTERS)
		setPage(1)
	}
	// The table's own total shrinks while searching, so the card reads the
	// unfiltered count the dashboard already computes.
	const { data: dashboard } = useAdminDashboard()
	const toggleVerified = useToggleUserVerified()
	const { data: recipients } = useAdminKopokopoRecipients()

	const registeredPhones = new Set(
		(recipients ?? [])
			.filter(
				(recipient) =>
					recipient.type === "mobile_wallet" && recipient.phoneNumber
			)
			.map((recipient) => recipient.phoneNumber!)
	)

	function handleToggle(userId: string, nextVerified: boolean) {
		toggleVerified.mutate(
			{ userId, verified: nextVerified },
			{
				onSuccess: () =>
					toast.success(
						nextVerified ? "User verified" : "Verification removed"
					),
				onError: () => toast.error("Couldn't update this user"),
			}
		)
	}

	const columns: ColumnDef<AdminUser>[] = [
		{
			id: "user",
			header: "User",
			enableSorting: false,
			cell: ({ row }) => (
				<div className="flex items-center gap-3">
					<AvatarPreviewDialog
						src={row.original.avatar}
						alt={row.original.name}
						fallback={initials(row.original.name)}
						className="size-9 shrink-0"
						verified={row.original.verified}
					/>
					<span className="flex min-w-0 items-center gap-1 font-medium">
						<span className="min-w-0 truncate">{row.original.name}</span>
						{row.original.verified && (
							<VerifiedBadge className="size-3.5 shrink-0" />
						)}
					</span>
				</div>
			),
		},
		{
			accessorKey: "email",
			header: "Email",
			cell: ({ row }) => (
				<span className="text-muted-foreground">{row.original.email}</span>
			),
		},
		{
			accessorKey: "phone",
			header: "Phone",
			cell: ({ row }) => <span className="">{row.original.phone}</span>,
		},
		{
			id: "kopokopoRecipient",
			header: "M-Pesa recipient",
			enableSorting: false,
			cell: ({ row }) => (
				<KopokopoRecipientCell
					user={row.original}
					isRegistered={
						!!row.original.phone &&
						registeredPhones.has(normalizePhoneNumber(row.original.phone))
					}
				/>
			),
		},
		{
			accessorKey: "gender",
			header: "Gender",
			cell: ({ row }) => (
				<span className="capitalize">{row.original.gender}</span>
			),
		},
		{
			id: "referredBy",
			header: "Referred by",
			enableSorting: false,
			cell: ({ row }) => <ReferredByCell user={row.original} />,
		},
		{
			id: "installed",
			header: "Installed",
			enableSorting: false,
			cell: ({ row }) =>
				row.original.settings?.pwaInstalledAt ? (
					<Badge
						variant="secondary"
						className="border-transparent bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
						Installed
					</Badge>
				) : (
					<span className="text-xs text-muted-foreground">Not installed</span>
				),
		},
		{
			id: "notifications",
			header: "Notifications",
			enableSorting: false,
			cell: ({ row }) => <NotificationsCell user={row.original} />,
		},
		{
			accessorKey: "created_at",
			header: "Created At",
			cell: ({ row }) => <span className="">{row.original.createdAt}</span>,
		},
		{
			id: "flagged",
			header: "Flagged",
			enableSorting: false,
			cell: ({ row }) => <FlagCell user={row.original} />,
		},
		{
			id: "verified",
			header: "Verified",
			enableSorting: false,
			meta: { className: "text-right" },
			cell: ({ row }) => (
				<div className="flex justify-end">
					<Switch
						checked={row.original.verified}
						disabled={toggleVerified.isPending}
						onCheckedChange={(checked) =>
							handleToggle(row.original.id, checked)
						}
						aria-label={
							row.original.verified
								? `Remove verified badge from ${row.original.name}`
								: `Verify ${row.original.name}`
						}
					/>
				</div>
			),
		},
	]

	return (
		<>
			<Head title="Admin users" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Users"
					description="Find users, manage verified badges, referrers and M-Pesa payouts"
				/>

				<div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
					{dashboard ? (
						<AdminStatCard
							label="Total users"
							value={dashboard.totals.totalUsers}
							icon={Users}
						/>
					) : (
						<Skeleton className="h-20" />
					)}
				</div>

				<Card>
					<CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
						<div className="sm:col-span-2">
							{/* Search Start */}
							<Input
								label="Search name, email or phone"
								value={search}
								onChange={(event) => {
									setSearch(event.target.value)
									setPage(1)
								}}
							/>
							{/* Search End */}
						</div>
						{USER_FILTERS.map((filter) => (
							<div key={filter.key}>
								{/* Filter Select Start */}
								<SelectField
									label={filter.label}
									value={filters[filter.key]}
									onValueChange={(value) => {
										setFilters((current) => ({
											...current,
											[filter.key]: value as UserFilterValue,
										}))
										setPage(1)
									}}>
									<SelectItem value="any">Any</SelectItem>
									<SelectItem value="yes">{filter.yes}</SelectItem>
									<SelectItem value="no">{filter.no}</SelectItem>
								</SelectField>
								{/* Filter Select End */}
							</div>
						))}
						{hasActiveFilters && (
							<div className="flex items-center">
								<Button
									variant="ghost"
									onClick={resetFilters}>
									Clear filters
								</Button>
							</div>
						)}
					</CardContent>
				</Card>

				<Card className="overflow-hidden">
					<CardHeader className="pb-4">
						<CardTitle>Users</CardTitle>
					</CardHeader>
					<CardContent>
						<DataTable
							columns={columns}
							data={data?.data ?? []}
							emptyMessage={isLoading ? "Loading…" : "No users found"}
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
							getItemLabel={(user) => user.name}
						/>
					</CardContent>
				</Card>
			</div>
		</>
	)
}
