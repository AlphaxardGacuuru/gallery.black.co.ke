import { Head } from "@/lib/spa"
import { useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import toast from "@/lib/toast"
import axios from "@/lib/axios"
import InputError from "@/components/input-error"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import { useApp } from "@/contexts/AppContext"
import { invalidateAuth } from "@/middleware/auth"

/**
 * Password sign-in for the password login admin account. Only reachable
 * through the signed link the admin portal's "Log in with password" button
 * opens, and the form posts back to that same signed URL.
 */
export default function AdminLogin() {
	const { setLocalStorage } = useApp()
	const navigate = useNavigate()

	const [email, setEmail] = useState("")
	const [password, setPassword] = useState("")
	const [processing, setProcessing] = useState(false)
	const [errors, setErrors] = useState<Record<string, string>>({})

	function handleSubmit(event: React.SyntheticEvent<HTMLFormElement>) {
		event.preventDefault()
		setProcessing(true)
		setErrors({})

		axios
			.post(`admin-login${window.location.search}`, { email, password })
			.then((response) => {
				if (response.data.two_factor) {
					navigate({
						to: "/two-factor-challenge",
						search: { pending_token: response.data.data },
					})

					return
				}

				setLocalStorage("sanctumToken", response.data.data)
				invalidateAuth()
				toast.success(response.data.message ?? "Logged in")
				navigate({ to: "/admin/dashboard" })
			})
			.catch((err: unknown) => {
				const e = err as {
					response?: {
						status?: number
						data?: { errors?: Record<string, string | string[]> }
					}
				}

				if (e.response?.status === 422) {
					const raw = e.response.data?.errors ?? {}

					setErrors(
						Object.fromEntries(
							Object.entries(raw).map(([k, v]) => [
								k,
								Array.isArray(v) ? String(v[0] ?? "") : String(v),
							])
						)
					)

					return
				}

				if (e.response?.status === 403) {
					toast.error("This link has expired. Open it again from the admin portal.")
					navigate({ to: "/login" })

					return
				}

				if (e.response?.status === 429) {
					toast.error("Too many attempts. Wait a minute and try again.")

					return
				}

				toast.error("Unable to log in")
			})
			.finally(() => setProcessing(false))
	}

	return (
		<>
			<Head title="Admin log in" />

			<form
				onSubmit={handleSubmit}
				className="flex flex-col gap-6"
				aria-busy={processing}>
				<div className="grid gap-6">
					<div className="grid gap-2">
						{/* Email Start */}
						<Input
							label="Email"
							type="email"
							value={email}
							onChange={(e) => setEmail(e.target.value)}
							autoComplete="username"
							autoFocus
							required
						/>
						{/* Email End */}
						<InputError message={errors.email} />
					</div>

					<div className="grid gap-2">
						{/* Password Start */}
						<Input
							label="Password"
							type="password"
							value={password}
							onChange={(e) => setPassword(e.target.value)}
							autoComplete="current-password"
							required
						/>
						{/* Password End */}
						<InputError message={errors.password} />
					</div>

					<Button
						type="submit"
						className="w-full"
						disabled={processing}>
						{processing && <Spinner />}
						Log in
					</Button>
				</div>
			</form>
		</>
	)
}

AdminLogin.layout = {
	title: "Admin log in",
	description: "Sign in with the admin password",
}
