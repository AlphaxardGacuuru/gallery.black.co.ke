import { UserProfile } from "@/components/profile/UserProfile"

type Props = {
	id: string
}

/** Any user's profile, opened by tapping their avatar or name. */
export default function UserShow({ id }: Props) {
	return <UserProfile userId={id} />
}
