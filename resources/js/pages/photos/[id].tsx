import { Navigate } from "@tanstack/react-router"

type Props = {
	id: string
}

/** The photo view moved to /photos/:id/show. Emails, push notifications
 *  and in-app notifications sent before the move still link here, so
 *  forward them instead of 404ing. */
export default function PhotoRedirect({ id }: Props) {
	return (
		<Navigate
			to={`/photos/${id}/show` as never}
			replace
		/>
	)
}
