// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import PersonSearch from '../../src/components/PersonSearch.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path: string) => `/nextcloud${path}`,
}))

const NcAvatarStub = defineComponent({
	props: {
		displayName: { type: String, required: true },
		url: { type: String, default: undefined },
	},
	template: '<img class="person-avatar" :src="url" :alt="displayName">',
})

describe('PersonSearch', () => {
	it('keeps the fixed search heading and scrollable result list as sibling regions', () => {
		const wrapper = mount(PersonSearch, {
			props: {
				query: 'Anna',
				results: [{
					id: 42,
					guid: '2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC',
					matrixUserId: '@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:chat.church.tools',
					displayName: 'Anna Schmidt',
					imageUrl: null,
					info: '',
				}],
				loading: false,
				startingPersonId: null,
				error: '',
			},
			global: {
				stubs: {
					NcAvatar: NcAvatarStub,
					NcButton: true,
					NcInputField: true,
					NcLoadingIcon: true,
				},
			},
		})
		const search = wrapper.get('aside.person-search')

		expect(search.get('.person-search__heading').element.parentElement).toBe(search.element)
		expect(search.get('.person-search__results').element.parentElement).toBe(search.element)
	})

	it('renders Matrix profile pictures through the authenticated avatar route', () => {
		const mxc = 'mxc://chat.church.tools/AnnaAvatar'
		const wrapper = mount(PersonSearch, {
			props: {
				query: 'Anna',
				results: [{
					id: 42,
					guid: '2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC',
					matrixUserId: '@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:chat.church.tools',
					displayName: 'Anna Schmidt',
					imageUrl: mxc,
					info: '',
				}],
				loading: false,
				startingPersonId: null,
				error: '',
			},
			global: {
				stubs: {
					NcAvatar: NcAvatarStub,
					NcButton: true,
					NcInputField: true,
					NcLoadingIcon: true,
				},
			},
		})

		expect(wrapper.get('img.person-avatar').attributes('src')).toBe(
			`/nextcloud/apps/churchtools_chat/api/avatar?mxc=${encodeURIComponent(mxc)}`,
		)
	})
})
