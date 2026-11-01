import { SupportChat } from "@/components/support/support-chat"
import Heading from "@/components/heading"
import { Head } from "@/lib/spa"

/**
 * The help chat as a full page, where "support replied" notifications
 * link to (/support?ticket=<id>). Everywhere else it opens from the Help
 * button in a side panel.
 */
export default function Support() {
	const ticketId = new URLSearchParams(window.location.search).get("ticket")

	return (
		<>
			<Head title="Help & support" />

			<div className="mx-auto flex h-[calc(100dvh-10rem)] w-full max-w-2xl flex-col gap-4 p-4 md:h-[calc(100dvh-6rem)]">
				<Heading
					variant="small"
					title="Help & support"
					description="Our assistant answers right away. A person takes over when needed."
				/>
				<div className="min-h-0 flex-1 overflow-hidden rounded-2xl border bg-background">
					<SupportChat
						key={ticketId ?? "list"}
						initialTicketId={ticketId}
					/>
				</div>
			</div>
		</>
	)
}
