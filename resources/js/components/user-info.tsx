import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import VerifiedBadge from "@/components/verified-badge"
import { useInitials } from "@/hooks/use-initials"
import type { User } from "@/types"

export function UserInfo({
	user,
	showEmail = false,
	showRole = false,
}: {
	user: User
	showEmail?: boolean
	showRole?: boolean
}) {
	const getInitials = useInitials()

	return (
		<>
			<Avatar className="h-8 w-8 shrink-0 overflow-hidden rounded-full cursor-pointer">
				<AvatarImage
					src={user.avatar}
					alt={user.name}
				/>
				<AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
					{getInitials(user.name)}
				</AvatarFallback>
			</Avatar>
			<div className="grid flex-1 text-left text-sm leading-tight cursor-pointer">
				<span className="flex min-w-0 items-center gap-1 font-medium">
					<span className="min-w-0 truncate">{user.name}</span>
					{user.verified && <VerifiedBadge className="size-3.5 shrink-0" />}
				</span>
				{showEmail && (
					<span className="truncate text-xs text-muted-foreground">
						{user.email}
					</span>
				)}
				{/* Role Names Start */}
				{showRole && Array.isArray(user.roleNames) && (
					<div>
						{user.roleNames.map(
							(role: { roleNames: string[] }, key: number) => (
								<div key={key}>
									{role.roleNames?.map((roleName, index) => (
										<h6
											key={index}
											className="fs-6 d-inline text-wrap me-1">
											{roleName}
											{index < role.roleNames.length - 1 && ","}
										</h6>
									))}
								</div>
							)
						)}
					</div>
				)}
				{/* Role Names End */}
			</div>
		</>
	)
}
