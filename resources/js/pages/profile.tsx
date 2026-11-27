import { UserProfile } from "@/components/profile/UserProfile"
import { useApp } from "@/contexts/AppContext"

/** The signed-in user's own profile, linked from the main nav. */
export default function Profile() {
	const { auth } = useApp()

	return auth ? <UserProfile userId={String(auth.id)} /> : null
}
