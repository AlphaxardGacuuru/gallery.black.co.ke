import { format, formatDistanceToNow } from "date-fns"
import { cn } from "@/lib/utils"

type Props = {
	date: string
	className?: string
	/** Pass false to show the date without the time of day. */
	withTime?: boolean
}

/** "27 Nov 2026, 2:05 PM · 3 hours ago". */
export function PostedAt({ date, className, withTime = true }: Props) {
	const postedAt = new Date(date)

	return (
		<time
			dateTime={postedAt.toISOString()}
			className={cn("truncate text-xs text-muted-foreground", className)}>
			{format(postedAt, withTime ? "d MMM yyyy, h:mm a" : "d MMM yyyy")} ·{" "}
			{formatDistanceToNow(postedAt, { addSuffix: true })}
		</time>
	)
}
