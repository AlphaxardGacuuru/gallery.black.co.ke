import { useState } from "react"
import type { ColumnDef } from "@tanstack/react-table"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { DataTable } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { SelectField, SelectItem } from "@/components/ui/select"
import toast from "@/lib/toast"
import {
	type AddKopokopoRecipientPayload,
	type AdminKopokopoRecipient,
	type AdminKopokopoTransfer,
	type KopokopoRecipientType,
	useAddKopokopoRecipient,
	useAdminKopokopoRecipients,
	useAdminKopokopoTransfers,
	useSendKopokopoTransfer,
} from "@/queries/admin"

const RECIPIENT_TYPES: { value: KopokopoRecipientType; label: string }[] = [
	{ value: "mobile_wallet", label: "M-Pesa (phone number)" },
	{ value: "bank_account", label: "Bank account" },
	{ value: "till", label: "Till" },
	{ value: "paybill", label: "Paybill" },
]

function recipientTypeLabel(type: KopokopoRecipientType): string {
	return RECIPIENT_TYPES.find((option) => option.value === type)?.label ?? type
}

function recipientSummary(recipient: AdminKopokopoRecipient): string {
	switch (recipient.type) {
		case "mobile_wallet":
			return [recipient.firstName, recipient.lastName, recipient.phoneNumber]
				.filter(Boolean)
				.join(" · ")
		case "bank_account":
			return [recipient.accountName, recipient.accountNumber]
				.filter(Boolean)
				.join(" · ")
		case "till":
			return [recipient.tillName, recipient.tillNumber]
				.filter(Boolean)
				.join(" · ")
		case "paybill":
			return [
				recipient.paybillName,
				recipient.paybillNumber,
				recipient.paybillAccountNumber,
			]
				.filter(Boolean)
				.join(" · ")
		default:
			return "—"
	}
}

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

function AddRecipientForm() {
	const addRecipient = useAddKopokopoRecipient()
	const [type, setType] = useState<KopokopoRecipientType>("mobile_wallet")
	const [description, setDescription] = useState("")
	const [firstName, setFirstName] = useState("")
	const [lastName, setLastName] = useState("")
	const [email, setEmail] = useState("")
	const [phoneNumber, setPhoneNumber] = useState("")
	const [accountName, setAccountName] = useState("")
	const [accountNumber, setAccountNumber] = useState("")
	const [bankBranchRef, setBankBranchRef] = useState("")
	const [tillName, setTillName] = useState("")
	const [tillNumber, setTillNumber] = useState("")
	const [paybillName, setPaybillName] = useState("")
	const [paybillNumber, setPaybillNumber] = useState("")
	const [paybillAccountNumber, setPaybillAccountNumber] = useState("")

	function reset() {
		setFirstName("")
		setLastName("")
		setEmail("")
		setPhoneNumber("")
		setAccountName("")
		setAccountNumber("")
		setBankBranchRef("")
		setTillName("")
		setTillNumber("")
		setPaybillName("")
		setPaybillNumber("")
		setPaybillAccountNumber("")
		setDescription("")
	}

	function handleAdd() {
		if (!description.trim()) {
			toast.error("Enter a description")
			return
		}

		const payload: AddKopokopoRecipientPayload = {
			type,
			description: description.trim(),
		}

		if (type === "mobile_wallet") {
			if (!phoneNumber.trim()) {
				toast.error("Enter a phone number")
				return
			}
			payload.firstName = firstName.trim() || undefined
			payload.lastName = lastName.trim() || undefined
			payload.email = email.trim() || undefined
			payload.phoneNumber = phoneNumber.trim()
		} else if (type === "bank_account") {
			if (!accountName.trim() || !accountNumber.trim()) {
				toast.error("Enter the account name and number")
				return
			}
			payload.accountName = accountName.trim()
			payload.accountNumber = accountNumber.trim()
			payload.bankBranchRef = bankBranchRef.trim() || undefined
		} else if (type === "till") {
			if (!tillNumber.trim()) {
				toast.error("Enter the till number")
				return
			}
			payload.tillName = tillName.trim() || undefined
			payload.tillNumber = tillNumber.trim()
		} else {
			if (!paybillNumber.trim() || !paybillAccountNumber.trim()) {
				toast.error("Enter the paybill number and account number")
				return
			}
			payload.paybillName = paybillName.trim() || undefined
			payload.paybillNumber = paybillNumber.trim()
			payload.paybillAccountNumber = paybillAccountNumber.trim()
		}

		addRecipient.mutate(payload, {
			onSuccess: () => {
				toast.success("Recipient added")
				reset()
			},
			onError: (error) =>
				toast.error("Couldn't add the recipient", {
					description: error.message,
				}),
		})
	}

	return (
		<Card className="max-w-md">
			<CardHeader>
				<CardTitle>Add recipient</CardTitle>
			</CardHeader>
			<CardContent className="space-y-4">
				<SelectField
					label="Type"
					value={type}
					onValueChange={(value) => setType(value as KopokopoRecipientType)}>
					{RECIPIENT_TYPES.map((option) => (
						<SelectItem
							key={option.value}
							value={option.value}>
							{option.label}
						</SelectItem>
					))}
				</SelectField>

				{type === "mobile_wallet" && (
					<>
						<Input
							label="First name"
							value={firstName}
							onChange={(event) => setFirstName(event.target.value)}
						/>
						<Input
							label="Last name"
							value={lastName}
							onChange={(event) => setLastName(event.target.value)}
						/>
						<Input
							label="Email (optional)"
							value={email}
							onChange={(event) => setEmail(event.target.value)}
						/>
						<Input
							label="Phone number"
							value={phoneNumber}
							onChange={(event) => setPhoneNumber(event.target.value)}
						/>
					</>
				)}

				{type === "bank_account" && (
					<>
						<Input
							label="Account name"
							value={accountName}
							onChange={(event) => setAccountName(event.target.value)}
						/>
						<Input
							label="Account number"
							value={accountNumber}
							onChange={(event) => setAccountNumber(event.target.value)}
						/>
						<Input
							label="Bank branch reference"
							value={bankBranchRef}
							onChange={(event) => setBankBranchRef(event.target.value)}
						/>
					</>
				)}

				{type === "till" && (
					<>
						<Input
							label="Till name"
							value={tillName}
							onChange={(event) => setTillName(event.target.value)}
						/>
						<Input
							label="Till number"
							value={tillNumber}
							onChange={(event) => setTillNumber(event.target.value)}
						/>
					</>
				)}

				{type === "paybill" && (
					<>
						<Input
							label="Paybill name"
							value={paybillName}
							onChange={(event) => setPaybillName(event.target.value)}
						/>
						<Input
							label="Paybill number"
							value={paybillNumber}
							onChange={(event) => setPaybillNumber(event.target.value)}
						/>
						<Input
							label="Paybill account number"
							value={paybillAccountNumber}
							onChange={(event) => setPaybillAccountNumber(event.target.value)}
						/>
					</>
				)}

				<Input
					label="Description"
					value={description}
					onChange={(event) => setDescription(event.target.value)}
				/>

				<Button
					disabled={addRecipient.isPending}
					onClick={handleAdd}>
					Add recipient
				</Button>
			</CardContent>
		</Card>
	)
}

function RecipientsList() {
	const { data: recipients } = useAdminKopokopoRecipients()

	return (
		<Card className="max-w-md">
			<CardHeader>
				<CardTitle>Saved recipients</CardTitle>
			</CardHeader>
			<CardContent>
				{!recipients || recipients.length === 0 ? (
					<p className="text-sm text-muted-foreground">
						No recipients added yet.
					</p>
				) : (
					<ul className="space-y-3">
						{recipients.map((recipient) => (
							<li
								key={recipient.id}
								className="rounded-lg border p-3 text-sm">
								<p className="font-medium">
									{recipientTypeLabel(recipient.type)}
								</p>
								<p className="text-muted-foreground">
									{recipientSummary(recipient)}
								</p>
								{recipient.description && (
									<p className="mt-1 text-xs text-muted-foreground">
										{recipient.description}
									</p>
								)}
							</li>
						))}
					</ul>
				)}
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

				<div className="flex flex-wrap gap-6">
					<SendMoneyForm />
					<AddRecipientForm />
					<RecipientsList />
				</div>
				<TransferHistory />
			</div>
		</>
	)
}
