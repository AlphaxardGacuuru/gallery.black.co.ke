import type { ManualInstallGuide } from "@/lib/manual-install"
import { cn } from "@/lib/utils"

export default function ManualInstallSteps({
	guide,
	className,
}: {
	guide: ManualInstallGuide
	className?: string
}) {
	return (
		<div className={cn("space-y-3 text-left text-sm", className)}>
			{guide.intro && <p className="font-medium">{guide.intro}</p>}
			<ol className="space-y-2.5">
				{guide.steps.map((step, index) => (
					<li
						key={step}
						className="flex items-start gap-3">
						<span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
							{index + 1}
						</span>
						<span className="pt-0.5 leading-5">{step}</span>
					</li>
				))}
			</ol>
			{guide.note && (
				<p className="text-xs text-muted-foreground">{guide.note}</p>
			)}
		</div>
	)
}
