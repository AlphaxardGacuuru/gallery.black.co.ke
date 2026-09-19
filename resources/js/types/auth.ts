export type UserSettings = {
	installOnboardedAt?: string
	permissionsOnboardedAt?: string
	referralOnboardedAt?: string
	[key: string]: unknown
}

export type User = {
	id: number
	name: string
	email: string
	phone: string | null
	avatar?: string
	verified?: boolean
	email_verified_at: string | null
	twoFactorEnabled?: boolean
	referralsCount?: number
	settings?: UserSettings | null
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
