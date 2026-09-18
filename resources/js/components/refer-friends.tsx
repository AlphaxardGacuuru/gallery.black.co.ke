import { Share2 } from "lucide-react"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useApp } from "@/contexts/AppContext"
import { useClipboard } from "@/hooks/use-clipboard"
import toast from "@/lib/toast"

export default function ReferFriends() {
	const { auth } = useApp()
	const [, copy] = useClipboard()

	if (!auth?.id) {
		return null
	}

	const referralLink = `${window.location.origin}/register?ref=${auth.id}`
	const referralsCount = auth.referralsCount ?? 0

	async function handleShare() {
		if (navigator.share) {
			try {
				await navigator.share({
					title: "Black Gallery",
					text: "Join me on Black Gallery's weekly photo challenge!",
					url: referralLink,
				})
			} catch {
				// The user cancelled the share sheet — nothing to do.
			}
			return
		}

		if (await copy(referralLink)) {
			toast.success("Referral link copied")
		} else {
			toast.error("Couldn't copy the link")
		}
	}

	return (
		<div className="space-y-4">
			<Heading
				variant="small"
				title="Refer friends"
				description={
					referralsCount > 0
						? `You've referred ${referralsCount} ${referralsCount === 1 ? "friend" : "friends"} so far — keep it up.`
						: "Share your link — top referrers get rewarded."
				}
			/>
			<div className="flex flex-wrap items-center gap-2">
				<Input
					readOnly
					value={referralLink}
					onFocus={(event) => event.target.select()}
					className="min-w-0 flex-1"
				/>
				<Button
					type="button"
					onClick={handleShare}
					className="gap-2">
					<Share2 className="size-4" />
					Share
				</Button>
			</div>
		</div>
	)
}
