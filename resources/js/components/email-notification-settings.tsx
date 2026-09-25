import { useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import NotificationPreferencesController from "@/actions/App/Http/Controllers/Settings/NotificationPreferencesController"
import { Spinner } from "@/components/ui/spinner"
import { Switch } from "@/components/ui/switch"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

type PreferenceKey =
	| "competitionStartedNotification"
	| "competitionWonNotification"

const CATEGORIES: { key: PreferenceKey; label: string; description: string }[] =
	[
		{
			key: "competitionStartedNotification",
			label: "Challenge announcements",
			description: "Email me when a new weekly challenge opens or ends.",
		},
		{
			key: "competitionWonNotification",
			label: "Challenge results",
			description: "Email me when I win a challenge.",
		},
	]

export default function EmailNotificationSettings() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const [processingKey, setProcessingKey] = useState<PreferenceKey | null>(null)

	function isEnabled(key: PreferenceKey): boolean {
		const value = auth?.settings?.[key]

		return value === undefined ? true : Boolean(value)
	}

	async function handleCheckedChange(key: PreferenceKey, checked: boolean) {
		setProcessingKey(key)

		try {
			const { action, method } = NotificationPreferencesController.update.form()

			await Axios.request({
				url: action,
				method,
				data: {
					competitionStartedNotification: isEnabled(
						"competitionStartedNotification"
					),
					competitionWonNotification: isEnabled("competitionWonNotification"),
					[key]: checked,
				},
			})

			await queryClient.invalidateQueries({ queryKey: ["auth"] })
			toast.success(
				checked ? "Notifications enabled" : "Notifications disabled"
			)
		} catch {
			toast.error("Couldn't update your notification preferences")
		} finally {
			setProcessingKey(null)
		}
	}

	return (
		<div className="space-y-4">
			{CATEGORIES.map((category) => (
				<div
					key={category.key}
					className="flex items-center justify-between gap-4">
					<div className="space-y-1">
						<p className="text-sm font-medium">{category.label}</p>
						<p className="text-sm text-muted-foreground">
							{category.description}
						</p>
					</div>
					<div className="flex items-center gap-2">
						{processingKey === category.key && (
							<Spinner className="size-4 text-muted-foreground" />
						)}
						<Switch
							checked={isEnabled(category.key)}
							disabled={processingKey !== null}
							onCheckedChange={(checked) =>
								handleCheckedChange(category.key, checked)
							}
							aria-label={`Toggle ${category.label.toLowerCase()}`}
						/>
					</div>
				</div>
			))}
		</div>
	)
}
