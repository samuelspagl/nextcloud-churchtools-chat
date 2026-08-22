import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type { ReferenceExtractResponse, ReferencePreview } from '../types/reference'

const referenceCache = new Map<string, Promise<ReferencePreview | null>>()
const HTTP_LINK_PATTERN = /https?:\/\/[^\s<>()]+/iu

export function containsHttpLink(text: string): boolean {
	return HTTP_LINK_PATTERN.test(text)
}

export function extractReferencePreview(text: string): Promise<ReferencePreview | null> {
	if (!containsHttpLink(text)) {
		return Promise.resolve(null)
	}

	const cached = referenceCache.get(text)
	if (cached) {
		return cached
	}

	const request = axios.post<ReferenceExtractResponse>(generateOcsUrl('references/extract'), {
		text,
		resolve: true,
		limit: 1,
	}).then((response) => {
		const reference = Object.values(response.data.ocs.data.references).find(Boolean) ?? null
		return reference?.accessible ? reference : null
	}).catch((error: unknown) => {
		referenceCache.delete(text)
		throw error
	})

	referenceCache.set(text, request)
	return request
}
