export type UserSettings = {
	installOnboardedAt?: string
	pwaInstalledAt?: string
	permissionsOnboardedAt?: string
	competitionStartedNotification?: boolean
	competitionWonNotification?: boolean
	referralSignupNotification?: boolean
	photoLikedNotification?: boolean
	photoSoldNotification?: boolean
	[key: string]: unknown
}

export type User = {
	id: number
	name: string
	email: string
	phone: string | null
	avatar?: string
	verified?: boolean
	/** Set while an admin has flagged the account for suspected abuse. */
	flaggedAt?: string | null
	/** When the user disputed the flag by updating their phone number. */
	flagRefutedAt?: string | null
	email_verified_at: string | null
	/** Whether the admin portal is open to this user (User::isAdmin()). */
	isAdmin?: boolean
	twoFactorEnabled?: boolean
	referralsCount?: number
	settings?: UserSettings | null
	/** Whether the AI usage admin page is open to this user (the viewAiUsage gate). */
	can_view_ai_usage?: boolean
	created_at: string
	updated_at: string
	[key: string]: unknown
}

export type TwoFactorSetupData = {
	svg: string
	url: string
}

export type TwoFactorSecretKey = {
	secretKey: string
}
