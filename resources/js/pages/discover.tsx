import { Compass } from "lucide-react"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { PhotoGrid } from "@/components/photos/PhotoGrid"
import { useDiscoverPhotos } from "@/queries/photos"

export default function Discover() {
	const query = useDiscoverPhotos()

	return (
		<>
			<Head title="Discover" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Discover"
					description="Winners and entries from past challenges"
				/>

				<PhotoGrid
					query={query}
					empty={
						<div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center text-muted-foreground">
							<Compass className="size-8" />
							<p className="font-medium">Nothing here yet</p>
							<p className="text-sm">
								Past competitions will show up here once they end.
							</p>
						</div>
					}
				/>
			</div>
		</>
	)
}
