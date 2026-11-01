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
 * The always-visible "Help" button, bottom right on every signed-in page
 * (above the mobile bottom nav), opening the help chat in a side panel.
 */
export function SupportLauncher({
	aboveFloatingAvatar = false,
}: {
	/** Stack above FloatingUserAvatar, which takes the same corner. */
	aboveFloatingAvatar?: boolean
}) {
	const { currentUrl } = useCurrentUrl()
	const [open, setOpen] = useState(false)

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
					"fixed right-4 z-40 inline-flex h-12 items-center gap-2 rounded-full bg-primary pr-5 pl-4 text-sm font-semibold text-primary-foreground shadow-lg ring-1 ring-black/5 transition-transform duration-200 hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
					aboveFloatingAvatar ? "bottom-36 md:bottom-20" : "bottom-24 md:bottom-6"
				)}
				aria-label="Get help">
				<MessageCircleQuestion className="size-5" />
				Help
			</button>
			<SheetContent
				side="right"
				className="h-dvh w-full gap-0 p-0 sm:max-w-md">
				<SheetHeader className="border-b pr-12">
					<SheetTitle>Help & support</SheetTitle>
					<SheetDescription>
						Our assistant answers right away. A person takes over when needed.
					</SheetDescription>
				</SheetHeader>
				<div className="min-h-0 flex-1">
					{open && <SupportChat />}
				</div>
			</SheetContent>
		</Sheet>
	)
}
