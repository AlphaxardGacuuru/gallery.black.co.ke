/** Mirrors Service::normalizePhoneNumber() on the backend so a raw phone
 *  number can be matched against the already-normalized numbers Kopokopo
 *  recipients are stored with. */
export function normalizePhoneNumber(phone: string): string {
	const digits = phone.replace(/\D/g, "")

	if (digits.startsWith("0") && digits.length === 10) {
		return `254${digits.slice(1)}`
	}

	return digits
}
