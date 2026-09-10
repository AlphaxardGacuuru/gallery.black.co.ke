export type UserSettings = {
	permissionsOnboardedAt?: string
	[key: string]: unknown
}

export type User = {
	id: number
	name: string
	email: string
	avatar?: string
	verified?: boolean
	email_verified_at: string | null
	twoFactorEnabled?: boolean
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
