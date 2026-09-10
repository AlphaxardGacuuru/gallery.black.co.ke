import { useState } from "react"
import Heading from "@/components/heading"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from "@/components/ui/table"
import VerifiedBadge from "@/components/verified-badge"
import { Head } from "@/lib/spa"
import toast from "@/lib/toast"
import { useAdminUsers, useToggleUserVerified } from "@/queries/admin"

function initials(name?: string | null): string {
	return (name?.trim() || "?").slice(0, 2).toUpperCase()
}

export default function AdminUsers() {
	const [search, setSearch] = useState("")
	const [page, setPage] = useState(1)
	const { data, isLoading } = useAdminUsers(search, page)
	const toggleVerified = useToggleUserVerified()

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

	return (
		<>
			<Head title="Admin users" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Users"
					description="Grant or revoke the verified badge shown next to a user's avatar"
				/>

				<Input
					label="Search by name"
					value={search}
					onChange={(event) => {
						setSearch(event.target.value)
						setPage(1)
					}}
				/>

				<div className="rounded-lg border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>User</TableHead>
								<TableHead>Email</TableHead>
								<TableHead className="text-right">Verified</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{isLoading &&
								Array.from({ length: 5 }).map((_, index) => (
									<TableRow key={index}>
										<TableCell colSpan={3}>
											<Skeleton className="h-10 w-full" />
										</TableCell>
									</TableRow>
								))}

							{!isLoading && (data?.data.length ?? 0) === 0 && (
								<TableRow>
									<TableCell
										colSpan={3}
										className="py-8 text-center text-muted-foreground">
										No users found
									</TableCell>
								</TableRow>
							)}

							{!isLoading &&
								data?.data.map((user) => (
									<TableRow key={user.id}>
										<TableCell>
											<div className="flex items-center gap-3">
												<Avatar className="size-9 shrink-0">
													<AvatarImage
														src={user.avatar ?? undefined}
														alt={user.name}
													/>
													<AvatarFallback>{initials(user.name)}</AvatarFallback>
												</Avatar>
												<span className="flex min-w-0 items-center gap-1 font-medium">
													<span className="min-w-0 truncate">{user.name}</span>
													{user.verified && (
														<VerifiedBadge className="size-3.5 shrink-0" />
													)}
												</span>
											</div>
										</TableCell>
										<TableCell className="text-muted-foreground">
											{user.email}
										</TableCell>
										<TableCell className="text-right">
											<Switch
												checked={user.verified}
												disabled={toggleVerified.isPending}
												onCheckedChange={(checked) =>
													handleToggle(user.id, checked)
												}
												aria-label={
													user.verified
														? `Remove verified badge from ${user.name}`
														: `Verify ${user.name}`
												}
											/>
										</TableCell>
									</TableRow>
								))}
						</TableBody>
					</Table>
				</div>

				{data && data.meta.last_page > 1 && (
					<div className="flex items-center justify-between">
						<p className="text-sm text-muted-foreground">
							Page {data.meta.current_page} of {data.meta.last_page}
						</p>
						<div className="flex gap-2">
							<Button
								variant="outline"
								size="sm"
								disabled={page <= 1}
								onClick={() => setPage((current) => current - 1)}>
								Previous
							</Button>
							<Button
								variant="outline"
								size="sm"
								disabled={page >= data.meta.last_page}
								onClick={() => setPage((current) => current + 1)}>
								Next
							</Button>
						</div>
					</div>
				)}
			</div>
		</>
	)
}
