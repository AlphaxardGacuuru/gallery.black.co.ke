import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import toast from "@/lib/toast"
import {
	type AdminKopokopoTransfer,
	useAdminKopokopoTransfers,
	useSendKopokopoTransfer,
} from "@/queries/admin"

const transferColumns: ColumnDef<AdminKopokopoTransfer>[] = [
	{
		accessorKey: "createdAt",
		header: "Sent",
	},
	{
		accessorKey: "user",
		header: "Recorded by",
		cell: ({ row }) => row.original.user ?? "—",
	},
	{
		accessorKey: "amount",
		header: "Amount",
		cell: ({ row }) =>
			`${row.original.currency ?? "KES"} ${row.original.amount}`,
	},
	{
		accessorKey: "kopokopoId",
		header: "Kopokopo ID",
		enableSorting: false,
		cell: ({ row }) => (
			<span className="text-muted-foreground">
				{row.original.kopokopoId ?? "—"}
			</span>
		),
	},
]

function SendMoneyForm() {
	const sendTransfer = useSendKopokopoTransfer()
	const [recipientName, setRecipientName] = useState("")
	const [phoneNumber, setPhoneNumber] = useState("")
	const [amount, setAmount] = useState("")
	const [description, setDescription] = useState("")

	function handleSend() {
		const parsedAmount = Number(amount)

		if (!recipientName.trim()) {
			toast.error("Enter the recipient's name")
			return
		}

		if (!phoneNumber.trim()) {
			toast.error("Enter a phone number")
			return
		}

		if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
			toast.error("Enter a valid amount")
			return
		}

		sendTransfer.mutate(
			{
				recipientName: recipientName.trim(),
				destinationReference: phoneNumber.trim(),
				amount: parsedAmount,
				description: description.trim() || undefined,
			},
			{
				onSuccess: () => {
					toast.success("Transfer initiated")
					setRecipientName("")
					setPhoneNumber("")
					setAmount("")
					setDescription("")
				},
				onError: (error) =>
					toast.error("Couldn't send the transfer", {
						description: error.message,
					}),
			}
		)
	}

	return (
		<Card className="max-w-md">
			<CardHeader>
				<CardTitle>Send money</CardTitle>
			</CardHeader>
			<CardContent className="space-y-4">
				<Input
					label="Recipient name"
					value={recipientName}
					onChange={(event) => setRecipientName(event.target.value)}
				/>
				<Input
					label="Phone number"
					value={phoneNumber}
					onChange={(event) => setPhoneNumber(event.target.value)}
				/>
				<Input
					type="number"
					min={1}
					label="Amount (KES)"
					value={amount}
					onChange={(event) => setAmount(event.target.value)}
				/>
				<Input
					label="Description (optional)"
					value={description}
					onChange={(event) => setDescription(event.target.value)}
				/>
				<Button
					disabled={sendTransfer.isPending}
					onClick={handleSend}>
					Send
				</Button>
			</CardContent>
		</Card>
	)
}

function TransferHistory() {
	const [page, setPage] = useState(1)
	const [perPage, setPerPage] = useState(20)
	const { data } = useAdminKopokopoTransfers(page, perPage)

	return (
		<Card className="overflow-hidden">
			<CardHeader className="pb-4">
				<CardTitle>Transfer history</CardTitle>
			</CardHeader>
			<CardContent>
				<DataTable
					columns={transferColumns}
					data={data?.data ?? []}
					emptyMessage="No transfers yet"
					pagination={{
						currentPage: data?.meta.current_page ?? 1,
						lastPage: data?.meta.last_page ?? 1,
						total: data?.meta.total ?? 0,
						pageSize: perPage,
						onPageChange: setPage,
						onPageSizeChange: (size) => {
							setPerPage(size)
							setPage(1)
						},
					}}
				/>
			</CardContent>
		</Card>
	)
}

export default function AdminKopokopoTransfers() {
	return (
		<>
			<Head title="Payouts" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Payouts"
					description="Send money via M-Pesa and review past transfers"
				/>

				<SendMoneyForm />
				<TransferHistory />
			</div>
		</>
	)
}
