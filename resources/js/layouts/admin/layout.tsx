import type { PropsWithChildren } from "react"

export default function AdminLayout({ children }: PropsWithChildren) {
	return <div className="space-y-6 px-4 py-6">{children}</div>
}
