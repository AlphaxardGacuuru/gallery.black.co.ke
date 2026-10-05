import type { ReactNode } from "react"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from "@/components/ui/dialog"

type Props = {
	src?: string | null
	alt: string
	fallback: ReactNode
	className?: string
	fallbackClassName?: string
}

/** A small avatar that opens its full-size image in a dialog when clicked.
 *  Falls back to a plain (non-clickable) avatar when there's no image to
 *  show, since the fallback initials aren't worth a popup. */
export function AvatarPreviewDialog({
	src,
	alt,
	fallback,
	className,
	fallbackClassName,
}: Props) {
	const avatar = (
		<Avatar className={className}>
			<AvatarImage
				src={src ?? undefined}
				alt={alt}
			/>
			<AvatarFallback className={fallbackClassName}>{fallback}</AvatarFallback>
		</Avatar>
	)

	if (!src) {
		return avatar
	}

	return (
		<Dialog>
			<DialogTrigger asChild>
				<button
					type="button"
					aria-label={`View ${alt}'s profile photo`}
					className="shrink-0 cursor-pointer rounded-full">
					{avatar}
				</button>
			</DialogTrigger>
			<DialogContent className="max-w-sm gap-0 overflow-hidden border-0 bg-black p-0 sm:max-w-md">
				<DialogTitle className="sr-only">{alt}&apos;s profile photo</DialogTitle>
				<img
					src={src}
					alt={alt}
					className="max-h-[80vh] w-full object-contain"
				/>
			</DialogContent>
		</Dialog>
	)
}
