import { normalizePhoneNumber } from "@/lib/phone"
import { cn } from "@/lib/utils"

/**
 * A phone number that starts a call when tapped. Dials the international
 * form (+2547...) so it works whatever country the caller's phone is set to.
 */
export function PhoneLink({
	phone,
	className,
}: {
	phone: string
	className?: string
}) {
	return (
		<a
			href={`tel:+${normalizePhoneNumber(phone)}`}
			className={cn("underline-offset-4 hover:underline", className)}
			title={`Call ${phone}`}>
			{phone}
		</a>
	)
}
