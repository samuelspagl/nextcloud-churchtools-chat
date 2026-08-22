import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path: string) => `/nextcloud${path}`),
}))

import { generateUrl } from '@nextcloud/router'
import { displayableAvatarUrl } from '../../src/utils/avatar'

describe('displayableAvatarUrl', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('proxies valid Matrix media URIs through the authenticated app route', () => {
		const mxc = 'mxc://chat.church.tools/AbC_123-xyz'

		expect(displayableAvatarUrl(mxc)).toBe(
			`/nextcloud/apps/churchtools_chat/api/avatar?mxc=${encodeURIComponent(mxc)}`,
		)
		expect(generateUrl).toHaveBeenCalledWith('/apps/churchtools_chat/api/avatar')
	})

	it('keeps existing HTTP and HTTPS avatar URLs displayable', () => {
		expect(displayableAvatarUrl('https://example.test/person.jpg')).toBe('https://example.test/person.jpg')
		expect(displayableAvatarUrl('http://example.test/person.jpg')).toBe('http://example.test/person.jpg')
	})

	it('rejects malformed MXC URIs and unsupported protocols', () => {
		expect(displayableAvatarUrl('mxc://chat.church.tools/with/slash')).toBeUndefined()
		expect(displayableAvatarUrl('mxc://chat.church.tools/')).toBeUndefined()
		expect(displayableAvatarUrl('data:image/png;base64,abc')).toBeUndefined()
		expect(displayableAvatarUrl('javascript:alert(1)')).toBeUndefined()
	})

	it('falls back to initials when no avatar URL exists', () => {
		expect(displayableAvatarUrl(null)).toBeUndefined()
		expect(displayableAvatarUrl('')).toBeUndefined()
	})
})
