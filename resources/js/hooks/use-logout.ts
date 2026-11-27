import { useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import axios from "@/lib/axios"
import toast from "@/lib/toast"
import { clearAuth } from "@/middleware/auth"
import { logout as logoutRoute } from "@/routes"

/**
 * Logs the user out and sends them to /login. Even if the request fails,
 * the local session is cleared so they're never stuck half signed in.
 */
export function useLogout() {
	const navigate = useNavigate()
	const [loggingOut, setLoggingOut] = useState(false)

	function logout() {
		setLoggingOut(true)

		const route = logoutRoute()

		axios
			.request({
				url: route.url,
				method: route.method,
			})
			.then((response) => {
				toast.success(response.data?.message ?? "Logged out")
			})
			.catch((error: unknown) => {
				const message =
					error &&
					typeof error === "object" &&
					"response" in error &&
					error.response &&
					typeof (error as any).response === "object"
						? (error as any).response?.data?.message
						: null

				toast.error(message ?? "Unable to log out")
			})
			.finally(() => {
				clearAuth()
				navigate({ to: "/login" })
				setLoggingOut(false)
			})
	}

	return { logout, loggingOut }
}
