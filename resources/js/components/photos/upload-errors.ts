import { isAxiosError } from "axios"

export function errorMessage(error: unknown, fallback: string): string {
	if (
		isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)
	) {
		return (
			error.response?.data.errors?.competition?.[0] ??
			error.response?.data.message ??
			fallback
		)
	}

	return error instanceof Error ? error.message : fallback
}

/** FilePond's own `error()` callback only shows whatever string we pass it,
 *  so a request that fails before reaching our .catch() (a dropped
 *  connection, a proxy rejecting an oversized body before Laravel ever
 *  sees it) needs to be told apart from a real 422 validation message,
 *  otherwise every failure looks identical and undiagnosable. */
export function filePondErrorMessage(error: unknown, fallback: string): string {
	if (
		!isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(
			error
		)
	) {
		return error instanceof Error ? error.message : fallback
	}

	if (!error.response) {
		return "Couldn't reach the server. Check your connection and try again."
	}

	if (error.response.status === 413) {
		return "That photo is too large to upload."
	}

	return (
		error.response.data.errors?.["filepond-photo"]?.[0] ??
		error.response.data.message ??
		`${fallback} (error ${error.response.status})`
	)
}
