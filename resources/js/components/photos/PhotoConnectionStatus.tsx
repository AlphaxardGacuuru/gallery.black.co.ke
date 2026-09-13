import { useConnectionStatus } from "@laravel/echo-react"
import { cn } from "@/lib/utils"

function connectionLabel(status: string): string {
	switch (status) {
		case "connected":
			return "Online"
		case "connecting":
			return "Connecting…"
		default:
			return "Offline"
	}
}

/**
 * Pill showing the realtime websocket connection state.
 */
export default function ChatConnectionStatus() {
	const status = useConnectionStatus()
	const connected = status === "connected"

	return (
		<div
			className={cn(
				"flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium",
				connected
					? "border-primary/30 bg-primary/10 text-primary"
					: "border-secondary-foreground/20 bg-secondary text-secondary-foreground"
			)}>
			<span className="relative flex size-1.5">
				{connected && (
					<span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75" />
				)}
				<span
					className={cn(
						"relative inline-flex size-1.5 rounded-full",
						connected ? "bg-primary" : "bg-secondary-foreground/50"
					)}
				/>
			</span>
			{connectionLabel(status)}
		</div>
	)
}