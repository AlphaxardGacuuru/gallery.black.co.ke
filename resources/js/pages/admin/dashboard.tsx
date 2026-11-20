import {
	Activity,
	Banknote,
	Camera,
	Gift,
	HandCoins,
	Hourglass,
	Images,
	Scale,
	Send,
	ThumbsUp,
	Trophy,
	UserPlus,
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
import { Link } from "@/components/ui/link"
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

						<ActiveUsersCard counts={data.activeUsers} />

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
										{
											label: "Photo sales",
											amount: data.finance.revenueBreakdown.photoSales,
										},
									]}
								/>
							</div>
							<div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
								<AdminStatCard
									label="Winnings paid"
									value={data.finance.payoutsBreakdown.prizes}
									prefix="KES"
									icon={Trophy}
									tone="warning"
								/>
								<AdminStatCard
									label="Referral rewards paid"
									value={data.finance.payoutsBreakdown.referralRewards}
									prefix="KES"
									icon={Gift}
									tone="warning"
								/>
								<AdminStatCard
									label="Paid to photographers"
									value={data.finance.payoutsBreakdown.photographerPayouts}
									prefix="KES"
									icon={Camera}
									tone="warning"
								/>
								<AdminStatCard
									label="Other transfers"
									value={data.finance.payoutsBreakdown.otherTransfers}
									prefix="KES"
									icon={Send}
									tone="warning"
								/>
							</div>
						</div>

						<div className="space-y-4">
							<Heading
								variant="small"
								title="Referrals"
								description="Sign-ups through referral links and their rewards"
							/>
							{/* "Paid out to referrers" from the referrals page is the
							    same figure as "Referral rewards paid" above. */}
							<div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
								<AdminStatCard
									label="Total referrals"
									value={data.referrals.totalReferrals}
									icon={UserPlus}
									tone="success"
								/>
								<AdminStatCard
									label="Users who've referred someone"
									value={data.referrals.totalReferrers}
									icon={Users}
								/>
								<AdminStatCard
									label="Pending payouts"
									value={data.referrals.totalPendingPayout}
									prefix="KES"
									icon={Hourglass}
									tone="warning"
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

/** Real users who used the app recently, linking through to the logins page. */
function ActiveUsersCard({
	counts,
}: {
	counts: { day: number; week: number; month: number }
}) {
	const periods = [
		{ label: "Last 24 hours", value: counts.day },
		{ label: "Last 7 days", value: counts.week },
		{ label: "Last 30 days", value: counts.month },
	]

	return (
		<Card>
			<CardContent className="space-y-4">
				<div className="flex items-center justify-between gap-4">
					<div className="flex items-center gap-3">
						<div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
							<Activity className="size-5" />
						</div>
						<p className="text-sm font-medium">Active users</p>
					</div>
					<Link
						href="/admin/logins"
						variant="muted"
						size="none">
						See logins
					</Link>
				</div>
				<dl className="grid grid-cols-3 gap-4">
					{periods.map((period) => (
						<div
							key={period.label}
							className="flex flex-col-reverse">
							<dt className="text-sm leading-tight text-muted-foreground">
								{period.label}
							</dt>
							<dd className="text-2xl font-semibold tracking-tight tabular-nums">
								{period.value.toLocaleString()}
							</dd>
						</div>
					))}
				</dl>
			</CardContent>
		</Card>
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
