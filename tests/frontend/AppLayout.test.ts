// @vitest-environment happy-dom

import { shallowMount } from '@vue/test-utils'
import { defineComponent, shallowRef } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ChatRoom, ChatStatus } from '../../src/types/chat'
import App from '../../src/App.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/router')>(),
	generateUrl: (path: string) => path,
}))

vi.mock('@nextcloud/dialogs', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/dialogs')>(),
	showWarning: vi.fn(),
}))

let chatState: ReturnType<typeof createChatState>

vi.mock('../../src/composables/useChat', () => ({
	useChat: () => chatState,
}))

const room: ChatRoom = {
	id: '!room:chat.church.tools',
	name: 'Long conversation',
	avatarUrl: null,
	encrypted: false,
	kind: 'group',
	memberCount: 20,
	unreadCount: 0,
	lastMessage: null,
	events: [],
}

const status: ChatStatus = {
	configured: true,
	tenantUrl: 'https://example.church.tools',
	personId: 1,
	personGuid: 'guid',
	displayName: 'Test User',
	canChat: true,
	matrixConnected: true,
	matrixUserId: '@test:chat.church.tools',
	capabilities: {
		rooms: true,
		messages: true,
		send: true,
		directChat: true,
		markdown: true,
		smartPicker: true,
	},
}

function createChatState() {
	const action = vi.fn()
	return {
		status: shallowRef<ChatStatus | null>(status),
		sessionExpired: shallowRef(false),
		rooms: shallowRef<ChatRoom[]>([room]),
		activeRoomId: shallowRef<string | null>(room.id),
		activeRoom: shallowRef<ChatRoom | null>(room),
		messages: shallowRef([]),
		loading: shallowRef(false),
		loadingMessages: shallowRef(false),
		detailsOpen: shallowRef(false),
		roomDetails: shallowRef(null),
		loadingRoomDetails: shallowRef(false),
		roomDetailsError: shallowRef(''),
		error: shallowRef(''),
		personResults: shallowRef([]),
		searchingPersons: shallowRef(false),
		personSearchError: shallowRef(''),
		startingPersonId: shallowRef(null),
		conversationSearchResults: shallowRef([]),
		searchingConversations: shallowRef(false),
		conversationSearchError: shallowRef(''),
		messageSearchResults: shallowRef([]),
		searchingMessages: shallowRef(false),
		messageSearchError: shallowRef(''),
		focusedMessageId: shallowRef(null),
		replyTargets: shallowRef({}),
		selectRoom: action,
		searchPersons: action,
		clearPersonSearch: action,
		searchConversations: action,
		clearConversationSearch: action,
		searchMessages: action,
		focusMessage: action,
		startDirectChat: action,
		send: action,
		setTyping: action,
		retry: action,
		react: action,
		unreact: action,
		deleteMessage: action,
		loadOlderMessages: action,
		toggleDetails: action,
		closeDetails: action,
	}
}

const NcContentStub = defineComponent({ template: '<div><slot /></div>' })
const ConversationHeaderStub = defineComponent({ template: '<header aria-label="Conversation header" />' })
const MessageTimelineStub = defineComponent({ template: '<section aria-label="Messages" />' })
const MessageComposerStub = defineComponent({
	props: { disabled: { type: Boolean, default: false } },
	template: '<form aria-label="Message composer" :data-disabled="String(disabled)" />',
})

function mountApp() {
	return shallowMount(App, {
		global: {
			stubs: {
				NcContent: NcContentStub,
				ConversationHeader: ConversationHeaderStub,
				MessageTimeline: MessageTimelineStub,
				MessageComposer: MessageComposerStub,
				ConversationSidebar: true,
				ConversationDetailsSidebar: true,
				PersonSearch: true,
			},
		},
	})
}

describe('App chat layout', () => {
	beforeEach(() => {
		chatState = createChatState()
	})

	it('keeps header, message viewport, and composer in separate layout regions', () => {
		const wrapper = mountApp()
		const pane = wrapper.get('main.chat-pane')

		expect(pane.get('.chat-pane__header').get('[aria-label="Conversation header"]').attributes('aria-label')).toBe('Conversation header')
		expect(pane.get('.chat-pane__body').get('[aria-label="Messages"]').attributes('aria-label')).toBe('Messages')
		expect(pane.get('[aria-label="Message composer"]').attributes('aria-label')).toBe('Message composer')
	})

	it('hides the composer for connection errors', () => {
		chatState.error.value = 'Connection failed'
		const wrapper = mountApp()

		expect(wrapper.get('[role="status"]').text()).toContain('Connection failed')
		expect(wrapper.find('[aria-label="Message composer"]').exists()).toBe(false)
	})

	it('keeps a disabled composer for encrypted rooms', () => {
		chatState.activeRoom.value = { ...room, encrypted: true }
		const wrapper = mountApp()

		expect(wrapper.get('[aria-label="Message composer"]').attributes('data-disabled')).toBe('true')
		expect(wrapper.get('.chat-pane__body').text()).toContain('This room is encrypted')
	})
})
