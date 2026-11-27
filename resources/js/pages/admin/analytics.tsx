import { isAxiosError } from "axios"
import { ChartLine } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import Heading from "@/components/heading"
import {
	SupportComposer,
	TypingIndicator,
} from "@/components/support/support-message"
import { Card } from "@/components/ui/card"
import { Head } from "@/lib/spa"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import { type AnalyticsChatMessage, useAskAnalytics } from "@/queries/admin"

/** The server takes at most 20 messages, the question included. */
const HISTORY_LIMIT = 19

const SUGGESTIONS = [
	"How many people were active each day this week?",
	"What share of people who signed up this month entered the challenge?",
	"Are people coming back in the weeks after they sign up?",
	"How much did we make from photo sales and extra entries last week?",
	"Which pages do guests visit most?",
]

export default function AdminAnalytics() {
	const [messages, setMessages] = useState<AnalyticsChatMessage[]>([])
	const ask = useAskAnalytics()
	const bottomRef = useRef<HTMLDivElement>(null)

	useEffect(() => {
		bottomRef.current?.scrollIntoView({ behavior: "smooth" })
	}, [messages, ask.isPending])

	async function send(question: string) {
		const conversation: AnalyticsChatMessage[] = [
			...messages.slice(-HISTORY_LIMIT),
			{ role: "user", content: question },
		]

		setMessages((current) => [...current, { role: "user", content: question }])

		try {
			const { answer, toolCalls } = await ask.mutateAsync(conversation)

			setMessages((current) => [
				...current,
				{ role: "assistant", content: answer, toolCalls },
			])
		} catch (error) {
			// Take the question back off so the composer can restore it.
			setMessages((current) => current.slice(0, -1))
			toast.error(
				isAxiosError(error)
					? (error.response?.data?.errors?.messages?.[0] ??
							error.response?.data?.message ??
							"Couldn't get an answer")
					: "Couldn't get an answer"
			)

			throw error
		}
	}

	return (
		<>
			<Head title="Analytics" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Analytics"
					description="Ask how people use the app. Answers come from recorded activity and the app's own records."
				/>

				<Card className="flex h-[70vh] flex-col gap-0 overflow-hidden p-0">
					<div className="flex-1 space-y-4 overflow-y-auto p-4">
						{messages.length === 0 ? (
							<div className="flex h-full flex-col items-center justify-center gap-4 text-center">
								<ChartLine className="size-8 text-muted-foreground" />
								<p className="text-sm text-muted-foreground">
									Try one of these, or ask your own.
								</p>
								<div className="flex max-w-xl flex-wrap justify-center gap-2">
									{SUGGESTIONS.map((suggestion) => (
										<button
											key={suggestion}
											type="button"
											disabled={ask.isPending}
											onClick={() => send(suggestion).catch(() => {})}
											className="cursor-pointer rounded-full border px-3 py-1.5 text-xs hover:bg-muted disabled:opacity-50">
											{suggestion}
										</button>
									))}
								</div>
							</div>
						) : (
							messages.map((message, index) => (
								<ChatMessage
									key={index}
									message={message}
								/>
							))
						)}
						{ask.isPending && <TypingIndicator />}
						<div ref={bottomRef} />
					</div>

					{/* Question Start */}
					<SupportComposer
						onSend={send}
						sending={ask.isPending}
						placeholder="Ask about sign-ups, activity, revenue…"
						maxLength={4000}
						autoFocus
					/>
					{/* Question End */}
				</Card>
			</div>
		</>
	)
}

function ChatMessage({ message }: { message: AnalyticsChatMessage }) {
	const mine = message.role === "user"

	return (
		<div
			className={cn("flex flex-col gap-1", mine ? "items-end" : "items-start")}>
			<div
				className={cn(
					"max-w-[85%] rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap wrap-break-word",
					mine
						? "rounded-br-sm bg-primary text-primary-foreground"
						: "rounded-bl-sm bg-muted text-foreground"
				)}>
				{message.content}
			</div>
			{message.toolCalls && message.toolCalls.length > 0 && (
				<details className="max-w-[85%] px-1 text-[11px] text-muted-foreground">
					<summary className="cursor-pointer">
						Based on {message.toolCalls.length}{" "}
						{message.toolCalls.length === 1 ? "lookup" : "lookups"}
					</summary>
					<ul className="mt-1 space-y-0.5 font-mono">
						{message.toolCalls.map((call, index) => (
							<li key={index}>
								{call.name}({describeArguments(call.arguments)})
							</li>
						))}
					</ul>
				</details>
			)}
		</div>
	)
}

function describeArguments(args: Record<string, unknown>): string {
	return Object.entries(args)
		.map(
			([key, value]) =>
				`${key}: ${Array.isArray(value) ? value.join(" → ") : String(value)}`
		)
		.join(", ")
}
