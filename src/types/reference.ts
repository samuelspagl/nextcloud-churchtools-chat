export interface ReferenceOpenGraphObject {
	id: string
	name: string
	description: string | null
	thumb: string | null
	link: string
}

export interface ReferencePreview {
	richObjectType: string
	richObject: Record<string, Record<string, unknown> | null>
	openGraphObject: ReferenceOpenGraphObject
	accessible: boolean
}

export interface ReferenceExtractResponse {
	ocs: {
		data: {
			references: Record<string, ReferencePreview | null>
		}
	}
}
