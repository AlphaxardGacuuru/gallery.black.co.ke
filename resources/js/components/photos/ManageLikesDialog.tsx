import { Heart } from "lucide-react"
import { useState } from "react"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import {
	Dialog,
	DialogContent,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import { useInitials } from "@/hooks/use-initials"
import { cn } from "@/lib/utils"
import { useAdminPhotoLikers, useToggleAdminPhotoLike } from "@/queries/admin"

/** Admin-only: browse/search users and toggle whether each one likes this
 *  photo, on their behalf (a moderation tool, not the public like button). */
export function ManageLikesDialog({
	photoId,
	open,
	onOpenChange,
}: {
	photoId: string
	open: boolean
	onOpenChange: (open: boolean) => void
}) {
	const [search, setSearch] = useState("")
	const { data: users, isLoading } = useAdminPhotoLikers(photoId, search)
	const toggleLike = useToggleAdminPhotoLike(photoId)
	const getInitials = useInitials()

	return (
		<Dialog
			open={open}
			onOpenChange={onOpenChange}>
			<DialogContent className="flex max-h-[80vh] flex-col gap-4">
				<DialogHeader>
					<DialogTitle>Manage likes</DialogTitle>
				</DialogHeader>
				<Input
					label="Search by name"
					value={search}
					onChange={(event) => setSearch(event.target.value)}
				/>
				<div className="min-h-0 flex-1 space-y-1 overflow-y-auto">
					{isLoading ? (
						<div className="flex justify-center py-6">
							<Spinner className="size-5 text-muted-foreground" />
						</div>
					) : !users || users.length === 0 ? (
						<p className="py-6 text-center text-sm text-muted-foreground">
							No users found.
						</p>
					) : (
						users.map((user) => (
							<button
								key={user.id}
								type="button"
								disabled={toggleLike.isPending}
								onClick={() => toggleLike.mutate(user.id)}
								className="flex w-full cursor-pointer items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-muted disabled:cursor-default disabled:opacity-60">
								<Avatar className="size-8 shrink-0">
									<AvatarImage
										src={user.avatar ?? undefined}
										alt={user.name}
									/>
									<AvatarFallback className="text-xs">
										{getInitials(user.name)}
									</AvatarFallback>
								</Avatar>
								<span className="min-w-0 flex-1 truncate font-medium">
									{user.name}
								</span>
								<Heart
									className={cn(
										"size-4 shrink-0 text-muted-foreground",
										user.likesPhoto && "fill-red-500 text-red-500"
									)}
								/>
							</button>
						))
					)}
				</div>
			</DialogContent>
		</Dialog>
	)
}
