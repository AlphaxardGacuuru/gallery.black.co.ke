import { MessageCircleQuestion } from "lucide-react"
import { useState } from "react"
import { SupportChat } from "@/components/support/support-chat"
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

/**
 * The always-visible "Feedback" button, bottom right on every signed-in page
 * (above the mobile bottom nav), opening the support chat in a side panel.
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

	// The full /support page is already the chat, and a full-screen photo
	// needs the corner clear.
	if (currentUrl.startsWith("/support") || isFullScreenRoute()) {
		return null
	}

	return (
		<Sheet
			open={open}
			onOpenChange={setOpen}>
			<button
				type="button"
				onClick={() => setOpen(true)}
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
				aria-label="Get help or share feedback">
				<MessageCircleQuestion className="size-5" />
				Feedback
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
				<div className="min-h-0 flex-1">
					{open && <SupportChat />}
				</div>
			</SheetContent>
		</Sheet>
	)
}
