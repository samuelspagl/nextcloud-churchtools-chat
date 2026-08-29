// @vitest-environment happy-dom

import { shallowMount } from '@vue/test-utils'
import { defineComponent, nextTick, shallowRef } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ChatRoom, GroupContextResponse } from '../../src/types/chat'

const context = shallowRef<GroupContextResponse | null>(null)
const loading = shallowRef(false)
const error = shallowRef('')
const retry = vi.fn()

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))
vi.mock('../../src/composables/useGroupContext', () => ({
	useGroupContext: () => ({ context, loading, error, retry }),
}))

import ConversationDetailsSidebar from '../../src/components/ConversationDetailsSidebar.vue'

const room: ChatRoom = {
	id: '!group:server',
	name: 'Technik',
	avatarUrl: null,
	encrypted: false,
	kind: 'group',
	memberCount: 3,
	unreadCount: 0,
	lastMessage: null,
	events: [],
}

const ContainerStub = defineComponent({ template: '<div><slot /></div>' })
const ButtonStub = defineComponent({ inheritAttrs: false, emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' })
const NoteStub = defineComponent({ props: { text: String }, template: '<div class="note">{{ text }}</div>' })
const InputStub = defineComponent({ template: '<input>' })

function mountSidebar() {
	return shallowMount(ConversationDetailsSidebar, {
		props: {
			open: true,
			room,
			details: null,
			loading: false,
			error: '',
			searchQuery: '',
			searchResults: [],
			searching: false,
			searchError: '',
		},
		global: {
			stubs: {
				NcAppSidebar: ContainerStub,
				NcAppSidebarTab: ContainerStub,
				NcAvatar: true,
				NcButton: ButtonStub,
				NcInputField: InputStub,
				NcListItem: true,
				NcLoadingIcon: true,
				NcNoteCard: NoteStub,
				GroupContextDialog: true,
			},
		},
	})
}

describe('ConversationDetailsSidebar group context', () => {
	beforeEach(() => {
		context.value = null
		loading.value = false
		error.value = ''
		retry.mockClear()
	})

	it('shows the group information button only for an unambiguous match', async () => {
		const wrapper = mountSidebar()
		expect(wrapper.text()).not.toContain('Group information')

		context.value = { matchStatus: 'matched', group: null, nextcloud: { status: 'none', teams: [] } }
		await nextTick()
		expect(wrapper.text()).toContain('Group information')
	})

	it('shows an ambiguity hint instead of a button', async () => {
		context.value = { matchStatus: 'ambiguous', group: null, nextcloud: { status: 'unavailable', teams: [] } }
		const wrapper = mountSidebar()
		await nextTick()

		expect(wrapper.text()).toContain('Several ChurchTools groups have this name')
		expect(wrapper.findAll('button')).toHaveLength(0)
	})

	it('offers retry after a lookup failure', async () => {
		error.value = 'ChurchTools unavailable'
		const wrapper = mountSidebar()

		await wrapper.get('button').trigger('click')
		expect(wrapper.text()).toContain('ChurchTools unavailable')
		expect(retry).toHaveBeenCalledOnce()
	})
})
