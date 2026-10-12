import {
	Banknote,
	HandCoins,
	Images,
	Scale,
	ThumbsUp,
	Trophy,
	Users,
} from "lucide-react"
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"
import { Head } from "@/lib/spa"
import AdminStatCard from "@/components/admin/AdminStatCard"
import Heading from "@/components/heading"
import { Card, CardContent } from "@/components/ui/card"
import {
	ChartContainer,
	ChartTooltip,
	ChartTooltipContent,
	type ChartConfig,
} from "@/components/ui/chart"
import { Skeleton } from "@/components/ui/skeleton"
import { useAdminDashboard } from "@/queries/admin"

const chartConfig: ChartConfig = {
	submitted: { label: "Photos submitted", color: "hsl(var(--chart-1))" },
}

export default function AdminDashboard() {
	const { data, isLoading } = useAdminDashboard()

	return (
		<>
			<Head title="Admin dashboard" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Overview"
					description="Photo challenge activity across the app"
				/>

				{isLoading || !data ? (
					<div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
						{Array.from({ length: 8 }).map((_, index) => (
							<Skeleton
								key={index}
								className="h-20"
							/>
						))}
					</div>
				) : (
					<>
						<div className="grid grid-cols-1 gap-4 lg:grid-cols-4">
							<AdminStatCard
								label="Total users"
								value={data.totals.totalUsers}
								icon={Users}
							/>
							<AdminStatCard
								label="Competitions run"
								value={data.totals.totalCompetitions}
								icon={Trophy}
							/>
							<AdminStatCard
								label="Photos submitted"
								value={data.totals.totalPhotos}
								icon={Images}
								tone="success"
							/>
							<AdminStatCard
								label="Total likes"
								value={data.totals.totalLikes}
								icon={ThumbsUp}
							/>
						</div>

						<div className="space-y-4">
							<Heading
								variant="small"
								title="Money"
								description="Paid slot purchases against payouts Kopokopo has confirmed"
							/>
							<div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
								<AdminStatCard
									label="Revenue"
									value={data.finance.revenue}
									prefix="KES"
									icon={Banknote}
									tone="success"
								/>
								<AdminStatCard
									label="Paid out"
									value={data.finance.payouts}
									prefix="KES"
									icon={HandCoins}
									tone="warning"
								/>
								<AdminStatCard
									label={data.finance.net < 0 ? "Net loss" : "Net"}
									value={Math.abs(data.finance.net)}
									prefix="KES"
									icon={Scale}
									tone={data.finance.net < 0 ? "danger" : "success"}
								/>
							</div>
							<div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
								<BreakdownCard
									title="Revenue by source"
									rows={[
										{
											label: "Extra entries",
											amount: data.finance.revenueBreakdown.extraEntries,
										},
										{
											label: "Extra photos",
											amount: data.finance.revenueBreakdown.extraPhotos,
										},
									]}
								/>
								<BreakdownCard
									title="Payouts by type"
									rows={[
										{
											label: "Competition prizes",
											amount: data.finance.payoutsBreakdown.prizes,
										},
										{
											label: "Referral rewards",
											amount: data.finance.payoutsBreakdown.referralRewards,
										},
										{
											label: "Other transfers",
											amount: data.finance.payoutsBreakdown.otherTransfers,
										},
									]}
								/>
							</div>
						</div>

						<div className="rounded-lg border p-4">
							<Heading
								variant="small"
								title="Submissions — last 14 days"
							/>
							<ChartContainer
								config={chartConfig}
								className="h-64">
								<BarChart data={data.dailyVolume}>
									<CartesianGrid
										vertical={false}
										strokeDasharray="3 3"
									/>
									<XAxis
										dataKey="date"
										tickFormatter={(value: string) =>
											new Date(value).toLocaleDateString(undefined, {
												month: "short",
												day: "numeric",
											})
										}
										tickLine={false}
										axisLine={false}
									/>
									<YAxis
										allowDecimals={false}
										tickLine={false}
										axisLine={false}
									/>
									<ChartTooltip content={<ChartTooltipContent />} />
									<Bar
										dataKey="submitted"
										fill="var(--color-submitted)"
										radius={4}
									/>
								</BarChart>
							</ChartContainer>
						</div>
					</>
				)}
			</div>
		</>
	)
}

function BreakdownCard({
	title,
	rows,
}: {
	title: string
	rows: { label: string; amount: number }[]
}) {
	return (
		<Card>
			<CardContent className="space-y-3">
				<p className="text-sm font-medium">{title}</p>
				<dl className="space-y-2 text-sm">
					{rows.map((row) => (
						<div
							key={row.label}
							className="flex items-center justify-between gap-4">
							<dt className="text-muted-foreground">{row.label}</dt>
							<dd className="font-medium tabular-nums">
								KES {row.amount.toLocaleString()}
							</dd>
						</div>
					))}
				</dl>
			</CardContent>
		</Card>
	)
}
