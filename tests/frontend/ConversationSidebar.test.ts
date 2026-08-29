// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import ConversationSidebar from '../../src/components/ConversationSidebar.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

const NcAvatarStub = defineComponent({ template: '<span />' })
const NcButtonStub = defineComponent({ template: '<button><slot /><slot name="icon" /></button>' })
const NcInputFieldStub = defineComponent({ template: '<input>' })

describe('ConversationSidebar', () => {
	it('keeps the fixed heading and scrollable conversation list as sibling regions', () => {
		const wrapper = mount(ConversationSidebar, {
			props: {
				rooms: [{
					id: '!room:chat.church.tools', name: 'Room', avatarUrl: null, encrypted: false,
					kind: 'group', memberCount: 3, unreadCount: 0, lastMessage: null, events: [],
				}],
				activeRoomId: null,
				loading: false,
				canStartChat: true,
				query: '',
				searchResults: [],
				searching: false,
				searchError: '',
			},
			global: { stubs: { NcAvatar: NcAvatarStub, NcButton: NcButtonStub, NcInputField: NcInputFieldStub, NcLoadingIcon: true, NcIconSvgWrapper: true } },
		})
		const sidebar = wrapper.get('aside.conversation-sidebar')

		expect(sidebar.get('.conversation-sidebar__heading').element.parentElement).toBe(sidebar.element)
		expect(sidebar.get('.conversation-list').element.parentElement).toBe(sidebar.element)
	})

	it('emphasizes the conversation name without emphasizing the message preview', () => {
		const wrapper = mount(ConversationSidebar, {
			props: {
				rooms: [{
					id: '!room:chat.church.tools', name: 'Project Meeting', avatarUrl: null, encrypted: false,
					kind: 'group', memberCount: 3, unreadCount: 0,
					lastMessage: {
						id: '$message:chat.church.tools', sender: '@anna:chat.church.tools',
						senderName: 'Anna', body: 'Meeting notes are ready', timestamp: 1_700_000_000_000,
					},
					events: [],
				}],
				activeRoomId: null,
				loading: false,
				canStartChat: true,
				query: '',
				searchResults: [],
				searching: false,
				searchError: '',
			},
			global: { stubs: { NcAvatar: NcAvatarStub, NcButton: NcButtonStub, NcInputField: NcInputFieldStub, NcLoadingIcon: true, NcIconSvgWrapper: true } },
		})

		expect(wrapper.get('.conversation__topline strong').text()).toBe('Project Meeting')
		expect(wrapper.get('.conversation__preview').text()).toBe('Meeting notes are ready')
		expect(wrapper.get('.conversation__preview').element.tagName).toBe('SPAN')
	})

	it('groups a message hit below its matching conversation and emits it on selection', async () => {
		const message = {
			id: '$meeting:chat.church.tools',
			sender: '@anna:chat.church.tools',
			senderName: 'Anna',
			body: 'Meeting notes are ready',
			timestamp: 1_700_000_000_000,
		}
		const wrapper = mount(ConversationSidebar, {
			props: {
				rooms: [{
					id: '!meeting:chat.church.tools', name: 'Project Meeting', avatarUrl: null, encrypted: false,
					kind: 'group', memberCount: 3, unreadCount: 0, lastMessage: null, events: [],
				}],
				activeRoomId: null,
				loading: false,
				canStartChat: true,
				query: 'meeting',
				searchResults: [{ roomId: '!meeting:chat.church.tools', message }],
				searching: false,
				searchError: '',
			},
			global: { stubs: { NcAvatar: NcAvatarStub, NcButton: NcButtonStub, NcInputField: NcInputFieldStub, NcLoadingIcon: true, NcIconSvgWrapper: true } },
		})

		expect(wrapper.text()).toContain('Project Meeting')
		expect(wrapper.text()).toContain('Anna: Meeting notes are ready')
		await wrapper.get('button.conversation').trigger('click')
		expect(wrapper.emitted('selectSearchResult')).toEqual([[{ roomId: '!meeting:chat.church.tools', message }]])
	})

	it('shows a typing indicator instead of the last message preview', () => {
		const wrapper = mount(ConversationSidebar, {
			props: {
				rooms: [{
					id: '!dm:chat.church.tools', name: 'Anna', avatarUrl: null, encrypted: false,
					kind: 'direct', memberCount: 2, unreadCount: 0, lastMessage: null, events: [],
					typingUsers: [{ id: '@anna:chat.church.tools', displayName: 'Anna' }],
				}],
				activeRoomId: null,
				loading: false,
				canStartChat: true,
				query: '',
				searchResults: [],
				searching: false,
				searchError: '',
			},
			global: { stubs: { NcAvatar: NcAvatarStub, NcButton: NcButtonStub, NcInputField: NcInputFieldStub, NcLoadingIcon: true, NcIconSvgWrapper: true } },
		})

		expect(wrapper.text()).toContain('Anna is typing…')
		expect(wrapper.find('.conversation__preview--typing').exists()).toBe(true)
	})
})
