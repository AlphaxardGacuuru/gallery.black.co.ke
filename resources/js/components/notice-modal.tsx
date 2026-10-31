import {
	Camera,
	Gift,
	type LucideIcon,
	Megaphone,
	Sparkles,
	Trophy,
	UserPlus,
} from "lucide-react"
import { useEffect, useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import { useIsPermissionsStepSettled } from "@/components/permissions-onboarding-modal"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { useApp } from "@/contexts/AppContext"
import {
	type Notice,
	type NoticeAction,
	recordNoticeEvent,
	useNextNotice,
} from "@/queries/notices"

// Must match Notice::ICONS on the server.
const ICONS: Record<string, LucideIcon> = {
	megaphone: Megaphone,
	gift: Gift,
	trophy: Trophy,
	camera: Camera,
	sparkles: Sparkles,
	"user-plus": UserPlus,
}

// AppShell remounts this when switching layouts, and the fetched notice
// stays cached for the whole visit. Remembering what's been handled at
// module level stops it reopening (and counting another impression).
const handled = new Set<string>()

function handledKey(notice: Notice): string {
	return `${notice.id}:${notice.version}`
}

/**
 * Shows the one notice the server picked for this user (ads, announcements,
 * the referral push), after the install and notifications prompts are out
 * of the way. Notices are managed under Admin > Notices.
 */
export default function NoticeModal() {
	const { auth } = useApp()
	const navigate = useNavigate()
	const permissionsStepSettled = useIsPermissionsStepSettled()
	const { data: notice } = useNextNotice(auth?.id, permissionsStepSettled)
	const [open, setOpen] = useState(false)

	useEffect(() => {
		if (!notice || handled.has(handledKey(notice))) {
			return
		}

		handled.add(handledKey(notice))
		recordNoticeEvent(notice, "shown")
		setOpen(true)
	}, [notice])

	if (!notice) {
		return null
	}

	const Icon = ICONS[notice.icon] ?? Megaphone

	function close(action: NoticeAction) {
		if (!notice || !open) {
			return
		}

		recordNoticeEvent(notice, action)
		setOpen(false)
	}

	function handlePrimary() {
		if (!notice) {
			return
		}

		close("primary_click")

		if (!notice.primaryUrl) {
			return
		}

		if (notice.primaryUrl.startsWith("/")) {
			navigate({ to: notice.primaryUrl })
		} else {
			window.open(notice.primaryUrl, "_blank", "noopener,noreferrer")
		}
	}

	return (
		<Dialog
			open={open}
			onOpenChange={(next) => {
				if (!next) {
					close("dismissed")
				}
			}}>
			<DialogContent className="sm:max-w-sm">
				<div className="flex flex-col items-center gap-4 pt-2 text-center">
					<div className="flex size-16 items-center justify-center rounded-full bg-primary/10">
						<Icon className="size-8 text-primary" />
					</div>
					<DialogHeader className="items-center gap-2">
						<DialogTitle>{notice.title}</DialogTitle>
						<DialogDescription>{notice.body}</DialogDescription>
					</DialogHeader>
				</div>
				<DialogFooter className="sm:justify-center">
					{notice.secondaryLabel && (
						<Button
							type="button"
							variant="outline"
							onClick={() => close("secondary_click")}>
							{notice.secondaryLabel}
						</Button>
					)}
					<Button
						type="button"
						onClick={handlePrimary}>
						{notice.primaryLabel}
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
