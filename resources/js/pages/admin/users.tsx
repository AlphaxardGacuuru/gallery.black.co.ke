import { isAxiosError } from "axios"
import { Check, Loader2, Pencil, Trash2, Users } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import { PhoneLink } from "@/components/phone-link"
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
	useAdminUserSettings,
	useAdminUsers,
	useAttachReferrer,
	useDeleteUser,
	useFlagUser,
	useRequestUserPhone,
	useToggleUserVerified,
	useUpdateScreenNewSignups,
	useUpdateUserPhone,
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
			<div className="flex items-center gap-1.5">
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
				{user.phoneRequestsCount > 0 && (
					<Badge
						variant="secondary"
						className="tabular-nums"
						title={`Sent ${user.phoneRequestsCount} ${user.phoneRequestsCount === 1 ? "time" : "times"}${user.phoneRequestedAt ? `, last on ${new Date(user.phoneRequestedAt).toLocaleString()}` : ""}`}>
						Sent {user.phoneRequestsCount}×
					</Badge>
				)}
			</div>
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
 * Phone cell: the number plus a dialog to set or change it, for when a
 * user asks support to change their locked number. The server rejects
 * anything that isn't a Safaricom M-Pesa number and says why.
 */
function PhoneCell({ user }: { user: AdminUser }) {
	const [open, setOpen] = useState(false)
	const [phone, setPhone] = useState(user.phone ?? "")
	const updatePhone = useUpdateUserPhone()
	const error =
		isAxiosError<{ errors?: { phone?: string[] } }>(updatePhone.error) &&
		updatePhone.error.response?.data?.errors?.phone?.[0]

	function handleOpenChange(nextOpen: boolean) {
		setOpen(nextOpen)

		if (nextOpen) {
			setPhone(user.phone ?? "")
			updatePhone.reset()
		}
	}

	function submit(event: React.FormEvent<HTMLFormElement>) {
		event.preventDefault()

		updatePhone.mutate(
			{ userId: user.id, phone },
			{
				onSuccess: () => {
					toast.success(`Updated ${user.name}'s phone number`)
					setOpen(false)
				},
				onError: (mutationError) => {
					if (
						!isAxiosError(mutationError) ||
						mutationError.response?.status !== 422
					) {
						toast.error("Couldn't update the phone number")
					}
				},
			}
		)
	}

	return (
		<div className="flex items-center gap-1">
			{user.phone && <PhoneLink phone={user.phone} />}
			<Dialog
				open={open}
				onOpenChange={handleOpenChange}>
				<DialogTrigger asChild>
					<Button
						variant="ghost"
						size="icon"
						className="size-7 text-muted-foreground"
						aria-label={
							user.phone
								? `Change ${user.name}'s phone number`
								: `Add a phone number for ${user.name}`
						}>
						<Pencil className="size-3.5" />
					</Button>
				</DialogTrigger>
				<DialogContent>
					<form
						onSubmit={submit}
						className="grid gap-4">
						<DialogTitle>
							{user.phone ? "Change" : "Add"} {user.name}'s phone number
						</DialogTitle>
						<DialogDescription>
							Prizes, payouts and payment prompts go to this number, so it must
							be a Safaricom M-Pesa number. This works even when the number is
							locked on their profile.
						</DialogDescription>

						{/* Phone Start */}
						<Input
							label="M-Pesa phone number"
							value={phone}
							onChange={(event) => setPhone(event.target.value)}
							error={error || undefined}
							helperText="07XXXXXXXX, 01XXXXXXXX or +254XXXXXXXXX"
							inputMode="tel"
							autoComplete="off"
							autoFocus
						/>
						{/* Phone End */}

						<DialogFooter className="gap-2">
							<DialogClose asChild>
								<Button
									type="button"
									variant="secondary">
									Cancel
								</Button>
							</DialogClose>
							<Button
								type="submit"
								disabled={!phone.trim() || updatePhone.isPending}>
								{updatePhone.isPending && (
									<Loader2 className="size-3.5 animate-spin" />
								)}
								Save
							</Button>
						</DialogFooter>
					</form>
				</DialogContent>
			</Dialog>
		</div>
	)
}

const FLAG_REASONS = [
	"Multiple accounts",
	"Bought or bot likes",
	"Trading likes with other users",
	"Someone else's M-Pesa number",
	"Photo isn't theirs",
	"Suspicious sign-up",
]

const OTHER_FLAG_REASON = "other"

/**
 * Flag switch: turning it on opens a confirmation dialog with an
 * admin-only reason (their likes stop counting, they can't win or be paid,
 * and they're notified on every channel); turning it off lifts the flag.
 */
function FlagCell({ user }: { user: AdminUser }) {
	const [open, setOpen] = useState(false)
	const [reasonChoice, setReasonChoice] = useState("")
	const [otherReason, setOtherReason] = useState("")
	const flagUser = useFlagUser()
	const reason =
		reasonChoice === OTHER_FLAG_REASON ? otherReason.trim() : reasonChoice

	function resetReason() {
		setReasonChoice("")
		setOtherReason("")
	}

	function submit(flagged: boolean) {
		flagUser.mutate(
			{ userId: user.id, flagged, reason: reason || undefined },
			{
				onSuccess: () => {
					toast.success(
						flagged ? `${user.name} flagged` : `${user.name} unflagged`
					)
					setOpen(false)
					resetReason()
				},
				onError: () => toast.error("Couldn't update this user"),
			}
		)
	}

	return (
		<div
			className="flex items-center gap-1.5"
			title={user.flagReason ?? undefined}>
			<Switch
				checked={!!user.flaggedAt}
				disabled={flagUser.isPending}
				// Turning it on asks for confirmation first; turning it off
				// lifts the flag straight away.
				onCheckedChange={(checked) => (checked ? setOpen(true) : submit(false))}
				aria-label={
					user.flaggedAt ? `Unflag ${user.name}` : `Flag ${user.name}`
				}
			/>
			{user.flaggedUntilPhone && (
				<Badge
					variant="outline"
					title="Flagged by sign-up screening. Adding a phone number lifts it.">
					Needs phone
				</Badge>
			)}
			{user.flagRefutedAt && (
				<Badge
					variant="outline"
					title="Updated their phone number to dispute the flag">
					Refuted
				</Badge>
			)}
			<Dialog
				open={open}
				onOpenChange={(nextOpen) => {
					setOpen(nextOpen)

					if (!nextOpen) {
						resetReason()
					}
				}}>
				<DialogContent>
					<DialogTitle>Flag {user.name}?</DialogTitle>
					<DialogDescription>
						Their likes stop counting in the running challenge, and they can't
						like, win or be paid until you unflag them. They'll get an email,
						push and in-app notification asking them to update their phone
						number if it's a mistake.
					</DialogDescription>

					{/* Reason Start */}
					<SelectField
						label="Reason (the user sees this)"
						value={reasonChoice}
						onValueChange={setReasonChoice}>
						{FLAG_REASONS.map((flagReason) => (
							<SelectItem
								key={flagReason}
								value={flagReason}>
								{flagReason}
							</SelectItem>
						))}
						<SelectItem value={OTHER_FLAG_REASON}>Other</SelectItem>
					</SelectField>
					{/* Reason End */}

					{reasonChoice === OTHER_FLAG_REASON && (
						<>
							{/* Other Reason Start */}
							<Input
								label="What's the reason?"
								value={otherReason}
								onChange={(event) => setOtherReason(event.target.value)}
								maxLength={255}
								autoFocus
							/>
							{/* Other Reason End */}
						</>
					)}

					<DialogFooter className="gap-2">
						<DialogClose asChild>
							<Button variant="secondary">Cancel</Button>
						</DialogClose>
						<Button
							variant="destructive"
							disabled={!reason || flagUser.isPending}
							onClick={() => submit(true)}>
							{flagUser.isPending && (
								<Loader2 className="size-3.5 animate-spin" />
							)}
							Flag user
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</div>
	)
}

/**
 * The verified badge switch. Turning it on asks why first; turning it off
 * removes the badge straight away.
 */
function VerifyCell({ user }: { user: AdminUser }) {
	const [open, setOpen] = useState(false)
	const [reason, setReason] = useState("")
	const { data: settings } = useAdminUserSettings()
	const toggleVerified = useToggleUserVerified()

	function submit(verified: boolean) {
		toggleVerified.mutate(
			{ userId: user.id, verified, reason: verified ? reason : undefined },
			{
				onSuccess: () => {
					toast.success(verified ? "User verified" : "Verification removed")
					setOpen(false)
					setReason("")
				},
				onError: () => toast.error("Couldn't update this user"),
			}
		)
	}

	return (
		<div
			className="flex justify-end"
			title={user.verificationReason ?? undefined}>
			<Switch
				checked={user.verified}
				disabled={toggleVerified.isPending}
				onCheckedChange={(checked) => (checked ? setOpen(true) : submit(false))}
				aria-label={
					user.verified
						? `Remove verified badge from ${user.name}`
						: `Verify ${user.name}`
				}
			/>
			<Dialog
				open={open}
				onOpenChange={(nextOpen) => {
					setOpen(nextOpen)

					if (!nextOpen) {
						setReason("")
					}
				}}>
				<DialogContent>
					<DialogTitle>Verify {user.name}?</DialogTitle>
					<DialogDescription>
						The verified badge shows on their profile picture and photos, and
						they'll get a notification about it.
					</DialogDescription>

					{/* Reason Start */}
					<SelectField
						label="Reason"
						value={reason}
						onValueChange={setReason}>
						{(settings?.verificationReasons ?? []).map((option) => (
							<SelectItem
								key={option.value}
								value={option.value}>
								{option.label}
							</SelectItem>
						))}
					</SelectField>
					{/* Reason End */}

					<DialogFooter className="gap-2">
						<DialogClose asChild>
							<Button variant="secondary">Cancel</Button>
						</DialogClose>
						<Button
							disabled={!reason || toggleVerified.isPending}
							onClick={() => submit(true)}>
							{toggleVerified.isPending && (
								<Loader2 className="size-3.5 animate-spin" />
							)}
							Verify user
						</Button>
					</DialogFooter>
				</DialogContent>
			</Dialog>
		</div>
	)
}

/**
 * Delete button with a confirmation dialog. The server refuses admins and
 * anyone with payment records, and its reason is shown as the error toast.
 */
function DeleteUserCell({ user }: { user: AdminUser }) {
	const [open, setOpen] = useState(false)
	const deleteUser = useDeleteUser()

	function submit() {
		deleteUser.mutate(user.id, {
			onSuccess: () => {
				toast.success(`${user.name} deleted`)
				setOpen(false)
			},
			onError: (error) =>
				toast.error(
					(isAxiosError<{ errors?: { user?: string[] } }>(error) &&
						error.response?.data?.errors?.user?.[0]) ||
						"Couldn't delete this user"
				),
		})
	}

	return (
		<Dialog
			open={open}
			onOpenChange={setOpen}>
			<DialogTrigger asChild>
				<Button
					variant="ghost"
					size="icon"
					className="text-muted-foreground hover:text-destructive"
					aria-label={`Delete ${user.name}`}>
					<Trash2 className="size-4" />
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogTitle>Delete {user.name}?</DialogTitle>
				<DialogDescription>
					This permanently deletes their account, photos, likes and support
					conversations. Their likes stop counting in the running challenge.
					This can't be undone.
				</DialogDescription>

				<DialogFooter className="gap-2">
					<DialogClose asChild>
						<Button variant="secondary">Cancel</Button>
					</DialogClose>
					<Button
						variant="destructive"
						disabled={deleteUser.isPending}
						onClick={submit}>
						{deleteUser.isPending && (
							<Loader2 className="size-3.5 animate-spin" />
						)}
						Delete user
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

/**
 * Switches the AI agent that screens new sign-ups. It flags accounts that
 * look fake or duplicated until they add a phone number, and each number
 * can only be on one account.
 */
function ScreenNewSignupsToggle() {
	const { data: settings } = useAdminUserSettings()
	const updateScreenNewSignups = useUpdateScreenNewSignups()

	if (!settings) {
		return <Skeleton className="h-20" />
	}

	function handleChange(checked: boolean) {
		updateScreenNewSignups.mutate(checked, {
			onSuccess: () =>
				toast.success(
					checked
						? "New sign-ups will be screened"
						: "New sign-ups won't be screened"
				),
			onError: () => toast.error("Couldn't update the setting"),
		})
	}

	return (
		<div className="flex items-center justify-between gap-4 rounded-xl border bg-card p-4 shadow-xs">
			<div>
				<p className="text-sm font-medium">Screen new sign-ups</p>
				<p className="mt-1 text-sm text-muted-foreground">
					{settings.screenNewSignups
						? "AI flags accounts that look fake or duplicated until they add a phone number. Also needs AI agents on."
						: "Off. New accounts aren't checked."}
				</p>
			</div>
			{/* Screen New Sign-ups Start */}
			<Switch
				checked={settings.screenNewSignups}
				disabled={updateScreenNewSignups.isPending}
				onCheckedChange={handleChange}
				aria-label="Screen new sign-ups"
			/>
			{/* Screen New Sign-ups End */}
		</div>
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
	// Unfiltered, the card shows the dashboard's count of real users. Once a
	// search or filter is set it follows the table's matching total instead.
	const { data: dashboard } = useAdminDashboard()
	const totalUsers = hasActiveFilters
		? data?.meta.total
		: dashboard?.totals.totalUsers
	const { data: recipients } = useAdminKopokopoRecipients()

	const registeredPhones = new Set(
		(recipients ?? [])
			.filter(
				(recipient) =>
					recipient.type === "mobile_wallet" && recipient.phoneNumber
			)
			.map((recipient) => recipient.phoneNumber!)
	)

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
			cell: ({ row }) => <PhoneCell user={row.original} />,
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
			cell: ({ row }) => <VerifyCell user={row.original} />,
		},
		{
			id: "delete",
			header: "",
			enableSorting: false,
			meta: { className: "text-right" },
			cell: ({ row }) => (
				<div className="flex justify-end">
					<DeleteUserCell user={row.original} />
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
					{totalUsers !== undefined ? (
						<AdminStatCard
							label={hasActiveFilters ? "Matching users" : "Total users"}
							value={totalUsers}
							icon={Users}
						/>
					) : (
						<Skeleton className="h-20" />
					)}
					<div className="lg:col-span-2">
						<ScreenNewSignupsToggle />
					</div>
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
