import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Link } from "@/components/ui/link"
import { useApp } from "@/contexts/AppContext"
import { edit } from "@/routes/profile"

/**
 * Shown instead of liking when the viewer's account is flagged. A new
 * account flagged until it adds a phone number is asked to add one; any
 * other flag explains why, and how to dispute it by updating the number.
 */
export function FlaggedAccountDialog({
	open,
	onOpenChange,
}: {
	open: boolean
	onOpenChange: (open: boolean) => void
}) {
	const { auth } = useApp()
	const reason = auth?.flagReason ?? "unusual activity"

	return (
		<Dialog
			open={open}
			onOpenChange={onOpenChange}>
			<DialogContent>
				{auth?.flaggedUntilPhone ? (
					<DialogHeader>
						<DialogTitle>Add your phone number to like</DialogTitle>
						<DialogDescription>
							Add your M-Pesa phone number to start liking photos. It keeps
							likes fair, since each number can only be on one account, and it's
							where your prizes are sent.
						</DialogDescription>
					</DialogHeader>
				) : (
					<>
						<DialogHeader>
							<DialogTitle>Your account is flagged</DialogTitle>
							<DialogDescription>
								Your account has been flagged due to: {reason}. While it's
								flagged you can't like photos, your likes don't count and
								payouts are on hold.
							</DialogDescription>
						</DialogHeader>

						<p className="text-sm text-muted-foreground">
							{auth?.flagRefutedAt
								? "Thanks for updating your phone number. We're reviewing your account and will lift the flag if everything checks out."
								: "If this is a mistake, update your M-Pesa phone number and we'll review your account to remove the flag."}
						</p>
					</>
				)}

				<DialogFooter>
					<Button
						variant="outline"
						onClick={() => onOpenChange(false)}>
						Close
					</Button>
					{auth?.canChangePhone && (
						<Button asChild>
							<Link
								href={edit().url}
								variant="unstyled"
								onClick={() => onOpenChange(false)}>
								{auth.flaggedUntilPhone
									? "Add phone number"
									: "Update phone number"}
							</Link>
						</Button>
					)}
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
