import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: (path: string) => `/ocs/v2.php/${path}`,
}))

import { containsHttpLink, extractReferencePreview } from '../../src/services/referenceApi'

const mockedAxios = vi.mocked(axios)

function responseFor(url: string, accessible = true) {
	return {
		data: {
			ocs: {
				data: {
					references: {
						[url]: {
							richObjectType: 'deck-card',
							richObject: { card: { id: 6, title: 'Test card' } },
							openGraphObject: {
								id: url,
								name: 'Test card',
								description: null,
								thumb: null,
								link: url,
							},
							accessible,
						},
					},
				},
			},
		},
	}
}

describe('reference API', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('skips messages without an HTTP link', async () => {
		expect(containsHttpLink('A message without a link')).toBe(false)
		expect(await extractReferencePreview('A message without a link')).toBeNull()
		expect(mockedAxios.post).not.toHaveBeenCalled()
	})

	it('extracts and resolves at most one reference', async () => {
		const text = 'See https://cloud.example.test/apps/deck/card/6 and https://example.test'
		mockedAxios.post.mockResolvedValue(responseFor('https://cloud.example.test/apps/deck/card/6'))

		const reference = await extractReferencePreview(text)

		expect(reference?.richObjectType).toBe('deck-card')
		expect(mockedAxios.post).toHaveBeenCalledWith('/ocs/v2.php/references/extract', {
			text,
			resolve: true,
			limit: 1,
		})
	})

	it('deduplicates identical message text during the session', async () => {
		const text = 'Duplicate https://cloud.example.test/apps/deck/card/7'
		mockedAxios.post.mockResolvedValue(responseFor('https://cloud.example.test/apps/deck/card/7'))

		const [first, second] = await Promise.all([
			extractReferencePreview(text),
			extractReferencePreview(text),
		])

		expect(first).toEqual(second)
		expect(mockedAxios.post).toHaveBeenCalledTimes(1)
	})

	it('does not expose inaccessible references', async () => {
		const text = 'Private https://cloud.example.test/apps/deck/card/8'
		mockedAxios.post.mockResolvedValue(responseFor('https://cloud.example.test/apps/deck/card/8', false))

		expect(await extractReferencePreview(text)).toBeNull()
	})
})
