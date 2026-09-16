import { Loader2 } from "lucide-react"
import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import Heading from "@/components/heading"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
import VerifiedBadge from "@/components/verified-badge"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import {
	type AdminUser,
	useAddKopokopoRecipient,
	useAdminKopokopoRecipients,
	useAdminUsers,
	useToggleUserVerified,
} from "@/queries/admin"

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

/** Mirrors Service::normalizePhoneNumber() on the backend so a user's raw
 *  phone can be matched against the already-normalized numbers Kopokopo
 *  recipients are stored with. */
function normalizePhoneNumber(phone: string): string {
	const digits = phone.replace(/\D/g, "")

	if (digits.startsWith("0") && digits.length === 10) {
		return `254${digits.slice(1)}`
	}

	return digits
}

function nameParts(name: string): { firstName: string; lastName?: string } {
	const [firstName, ...rest] = name.trim().split(/\s+/)
	return { firstName, lastName: rest.join(" ") || undefined }
}

function KopokopoRecipientCell({
	user,
	isRegistered,
}: {
	user: AdminUser
	isRegistered: boolean
}) {
	const addRecipient = useAddKopokopoRecipient()

	if (isRegistered) {
		return <Badge variant="default">Recipient</Badge>
	}

	if (!user.phone) {
		return <span className="text-xs text-muted-foreground">No phone</span>
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
				onSuccess: () => toast.success(`${user.name} registered as a recipient`),
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

export default function AdminUsers() {
	const [search, setSearch] = useState("")
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data, isLoading } = useAdminUsers(search, page, perPage)
	const toggleVerified = useToggleUserVerified()
	const { data: recipients } = useAdminKopokopoRecipients()

	const registeredPhones = new Set(
		(recipients ?? [])
			.filter((recipient) => recipient.type === "mobile_wallet" && recipient.phoneNumber)
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
					<Avatar className="size-9 shrink-0">
						<AvatarImage
							src={row.original.avatar ?? undefined}
							alt={row.original.name}
						/>
						<AvatarFallback>{initials(row.original.name)}</AvatarFallback>
					</Avatar>
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
			accessorKey: "created_at",
			header: "Created At",
			cell: ({ row }) => <span className="">{row.original.createdAt}</span>,
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
					description="Grant or revoke the verified badge shown next to a user's avatar"
				/>

				<Card>
					<CardContent className="flex flex-wrap items-center gap-4">
						<Input
							label="Search by name"
							value={search}
							onChange={(event) => {
								setSearch(event.target.value)
								setPage(1)
							}}
						/>
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
