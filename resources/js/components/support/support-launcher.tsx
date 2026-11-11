import { useQueryClient } from "@tanstack/react-query"
import { Inbox, MessageCircleQuestion } from "lucide-react"
import { useState } from "react"
import { SupportChat } from "@/components/support/support-chat"
import { Link } from "@/components/ui/link"
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetHeader,
	SheetTitle,
} from "@/components/ui/sheet"
import { useCurrentUrl } from "@/hooks/use-current-url"
import { isFullScreenRoute } from "@/lib/bottom-nav"
import { cn } from "@/lib/utils"
import { useSupportPending } from "@/queries/support"

/**
 * The always-visible "Feedback" button, bottom right on every signed-in page
 * (above the mobile bottom nav), opening the support chat in a side panel.
 * A badge counts replies the user hasn't read and, for the admin, tickets
 * waiting on a person.
 */
export function SupportLauncher({
	aboveFloatingAvatar = false,
}: {
	/** Stack above FloatingUserAvatar, which takes the same corner. */
	aboveFloatingAvatar?: boolean
}) {
	const { currentUrl } = useCurrentUrl()
	const [open, setOpen] = useState(false)
	const onCompetePage = currentUrl === "/compete"
	const queryClient = useQueryClient()
	const { data: pending } = useSupportPending()
	const waiting = pending?.waiting ?? 0
	const badgeCount = (pending?.unread ?? 0) + waiting

	function handleOpenChange(next: boolean) {
		setOpen(next)
		queryClient.invalidateQueries({ queryKey: ["support-pending"] })
	}

	// The full /support page is already the chat, and a full-screen photo
	// needs the corner clear.
	if (currentUrl.startsWith("/support") || isFullScreenRoute()) {
		return null
	}

	return (
		<Sheet
			open={open}
			onOpenChange={handleOpenChange}>
			<button
				type="button"
				onClick={() => handleOpenChange(true)}
				className={cn(
					"fixed z-40 inline-flex h-12 items-center gap-2 rounded-full bg-primary pr-5 pl-4 text-sm font-semibold text-primary-foreground shadow-lg ring-1 ring-black/5 transition-transform duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
					// The compete page's SubmitPhotoButton (h-12) takes the same
					// spot, so stack 12px above it there.
					onCompetePage
						? "right-4 bottom-41 md:right-70 md:bottom-21"
						: aboveFloatingAvatar
							? "right-4 bottom-40 md:bottom-20"
							: "right-4 bottom-26 md:bottom-6"
				)}
				aria-label={
					badgeCount > 0
						? `Get help or share feedback, ${badgeCount} pending`
						: "Get help or share feedback"
				}>
				<MessageCircleQuestion className="size-5" />
				Feedback
				{badgeCount > 0 && (
					<span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[11px] leading-none font-semibold text-white ring-2 ring-background">
						{badgeCount > 99 ? "99+" : badgeCount}
					</span>
				)}
			</button>
			<SheetContent
				side="right"
				className="h-dvh w-full gap-0 p-0 sm:max-w-md">
				<SheetHeader className="border-b pr-12">
					<SheetTitle>Help & feedback</SheetTitle>
					<SheetDescription>
						Ask a question or suggest an idea. Our assistant answers right away and a person takes over when needed.
					</SheetDescription>
				</SheetHeader>
				{waiting > 0 && (
					<Link
						href="/admin/support"
						variant="unstyled"
						onClick={() => handleOpenChange(false)}
						className="flex items-center gap-2 border-b bg-muted/60 px-4 py-3 text-sm font-medium hover:bg-muted">
						<Inbox className="size-4" />
						{waiting} ticket{waiting === 1 ? "" : "s"} waiting in the Support inbox
					</Link>
				)}
				<div className="min-h-0 flex-1">
					{open && <SupportChat />}
				</div>
			</SheetContent>
		</Sheet>
	)
}
