import { Camera, Compass, Heart, Trophy } from "lucide-react"
import AppLogo from "@/components/app-logo"
import { BackdropLines } from "@/components/backdrop-lines"
import { CompetitionCountdown } from "@/components/photos/CompetitionCountdown"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { GlassCard, GlassInner } from "@/components/ui/glass-card"
import { Link } from "@/components/ui/link"
import { Head } from "@/lib/spa"
import { useApp } from "@/contexts/AppContext"
import { useCurrentCompetition } from "@/queries/photos"

const howItWorks = [
	{
		icon: Camera,
		title: "Submit your best shot",
		description:
			"Upload a photo to the weekly challenge any time between Monday and Friday.",
	},
	{
		icon: Heart,
		title: "Get liked by the community",
		description:
			"Everyone can browse entries and like the photos they think deserve to win.",
	},
	{
		icon: Trophy,
		title: "Most-liked photo wins the prize",
		description:
			"When the challenge closes Friday at 8pm, the top photo takes the cash prize.",
	},
]

export default function Welcome() {
	const { auth } = useApp()
	const { data: competition } = useCurrentCompetition()

	const primaryCta = auth
		? { href: "/compete", label: "View this week's challenge" }
		: { href: "/register", label: "Join the challenge" }

	return (
		<div className="relative isolate min-h-screen overflow-hidden bg-background text-foreground">
			<BackdropLines />

			<div className="pointer-events-none absolute inset-0 z-0 overflow-hidden">
				<div className="absolute -left-40 -top-28 h-80 w-80 rounded-full bg-primary/36 blur-3xl dark:bg-primary/28" />
				<div className="absolute -right-24 top-36 h-96 w-96 rounded-full bg-secondary/70 blur-3xl dark:bg-secondary/35" />
				<div className="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-foreground/18 blur-3xl dark:bg-foreground/12" />
			</div>

			<Head title="Black Gallery — the weekly photo challenge" />

			<header className="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-4 py-6 sm:px-6 lg:px-8">
				<Link
					href="/"
					variant="unstyled"
					size="none">
					<AppLogo className="h-16 w-auto" />
				</Link>
				<div className="flex items-center gap-2">
					{auth ? (
						<Link
							href="/compete"
							variant="solid">
							Go to challenge
						</Link>
					) : (
						<>
							<Link
								href="/login"
								variant="ghost">
								Sign in
							</Link>
							<Link
								href="/register"
								variant="solid">
								Join now
							</Link>
						</>
					)}
				</div>
			</header>

			<section className="relative z-10 mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 lg:px-8 lg:pb-24 lg:pt-16">
				<div className="grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
					<div className="space-y-8">
						<div className="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/8 px-4 py-2 text-sm text-primary shadow-sm backdrop-blur-sm">
							<Trophy className="size-4" />
							<span>A new photo challenge, every single week</span>
						</div>

						<div className="space-y-5">
							<h1 className="max-w-4xl text-5xl font-extrabold tracking-tight text-balance sm:text-6xl lg:text-7xl">
								<span className="bg-linear-to-r from-primary via-foreground to-primary bg-clip-text text-transparent dark:via-white">
									Snap it. Post it. Get liked. Win it.
								</span>
							</h1>
							<p className="max-w-2xl text-lg leading-8 text-muted-foreground sm:text-xl">
								Black Gallery runs a photography competition every week, Monday
								through Friday at 8pm. The most liked photo takes home the cash
								prize.
							</p>
						</div>

						{competition && (
							<div className="flex items-baseline gap-3">
								<p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
									This week&apos;s prize
								</p>
								<p className="text-4xl font-extrabold tracking-tight text-primary sm:text-5xl">
									KES {competition.prizeAmount}
								</p>
							</div>
						)}

						<div className="flex flex-wrap gap-3">
							<Link
								href={primaryCta.href}
								variant="solid"
								size="lg"
								className="shadow-lg shadow-primary/20">
								{primaryCta.label}
							</Link>
							<Link
								href="/discover"
								variant="outline"
								size="lg"
								className="gap-2 border-border/70 bg-background/70 backdrop-blur-sm">
								<Compass className="size-4" />
								See past winners
							</Link>
						</div>

						{competition && (
							<GlassCard className="inline-block p-4">
								<p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
									Time left to enter
								</p>
								<CompetitionCountdown endsAt={competition.endsAt} />
							</GlassCard>
						)}
					</div>

					<Card className="relative z-10 overflow-hidden">
						<CardHeader className="pb-3">
							<div className="flex items-center justify-between gap-3">
								<div>
									<p className="text-xs uppercase tracking-[0.24em] text-muted-foreground">
										How it works
									</p>
									<CardTitle className="mt-2 text-2xl">Every week</CardTitle>
								</div>
								<div className="rounded-2xl border border-primary/20 bg-primary/10 p-3 text-primary">
									<Trophy className="size-6" />
								</div>
							</div>
						</CardHeader>
						<CardContent className="space-y-3 pb-6">
							{howItWorks.map(({ icon: Icon, title, description }) => (
								<GlassInner
									key={title}
									className="flex items-start gap-3 p-4">
									<div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
										<Icon className="size-4" />
									</div>
									<div>
										<p className="text-sm font-medium">{title}</p>
										<p className="mt-1 text-sm text-muted-foreground">
											{description}
										</p>
									</div>
								</GlassInner>
							))}
						</CardContent>
					</Card>
				</div>
			</section>
		</div>
	)
}
