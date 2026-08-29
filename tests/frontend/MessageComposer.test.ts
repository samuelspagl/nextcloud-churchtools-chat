// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MessageComposer from '../../src/components/MessageComposer.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/router')>(),
	generateUrl: (path: string) => `/nextcloud${path}`,
}))

const showTribute = vi.fn()

const ROOM_DETAILS = {
	roomId: '!room:test',
	name: 'Room',
	avatarUrl: null,
	kind: 'group' as const,
	memberCount: 2,
	topic: '',
	canonicalAlias: null,
	encrypted: false,
	creator: null,
	joinRule: null,
	historyVisibility: null,
	members: [
		{ id: '@ct_anna:test', displayName: 'Anna', avatarUrl: 'mxc://chat.church.tools/anna-avatar', membership: 'join' as const },
		{ id: '@ct_bob:test', displayName: 'Bob', avatarUrl: null, membership: 'join' as const },
		{ id: '@ct_carla:test', displayName: 'Carla', avatarUrl: null, membership: 'invite' as const },
	],
}

const NcRichContenteditableStub = defineComponent({
	props: {
		modelValue: { type: String, default: '' },
		disabled: { type: Boolean, default: false },
		linkAutocomplete: { type: Boolean, default: false },
		emojiAutocomplete: { type: Boolean, default: true },
		placeholder: { type: String, default: '' },
		autoComplete: { type: Function, default: () => [] },
		userData: { type: Object, default: () => ({}) },
	},
	emits: ['update:modelValue', 'submit'],
	setup(_props, { emit, expose }) {
		expose({ showTribute })
		function update(event: Event) {
			emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
		}
		return { update }
	},
	template: '<textarea :value="modelValue" :disabled="disabled" :placeholder="placeholder" @input="update" />',
})

const NcEmojiPickerStub = defineComponent({
	props: {
		closeOnSelect: { type: Boolean, default: false },
	},
	emits: ['select', 'selectData', 'unselect'],
	template: '<div><slot /></div>',
})

const NcButtonStub = defineComponent({
	props: {
		disabled: { type: Boolean, default: false },
		type: { type: String, default: 'button' },
	},
	emits: ['click'],
	template: '<button :type="type" :disabled="disabled" @click="$emit(\'click\', $event)"><slot name="icon" /><slot /></button>',
})

const NcActionsStub = defineComponent({
	props: {
		ariaLabel: { type: String, default: '' },
		disabled: { type: Boolean, default: false },
	},
	data: () => ({ open: false }),
	template: `
		<div>
			<button type="button" :aria-label="ariaLabel" :disabled="disabled" @click="open = true"><slot name="icon" /></button>
			<div v-if="open"><slot /></div>
		</div>
	`,
})

const NcActionButtonStub = defineComponent({
	emits: ['click'],
	template: '<button type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
})

function mountComposer(disabled = false, pendingFiles: File[] = [], roomDetails: typeof ROOM_DETAILS | null = ROOM_DETAILS) {
	return mount(MessageComposer, {
		props: { disabled, pendingFiles, roomDetails },
		global: {
			stubs: {
				NcActionButton: NcActionButtonStub,
				NcActions: NcActionsStub,
				NcButton: NcButtonStub,
				NcEmojiPicker: NcEmojiPickerStub,
				NcIconSvgWrapper: true,
				NcRichContenteditable: NcRichContenteditableStub,
			},
		},
	})
}

describe('MessageComposer', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('opens the dynamic Smart Picker from the plus menu', async () => {
		const wrapper = mountComposer()

		await wrapper.get('button[aria-label="Add content"]').trigger('click')
		const smartPickerButton = wrapper.findAll('button').find((button) => button.text().includes('Smart Picker'))
		expect(smartPickerButton).toBeDefined()
		await smartPickerButton!.trigger('click')

		expect(showTribute).toHaveBeenCalledWith('/')
	})

	it('keeps slash autocomplete enabled and emoji autocomplete disabled', () => {
		const wrapper = mountComposer()
		const editor = wrapper.getComponent(NcRichContenteditableStub)

		expect(editor.props('linkAutocomplete')).toBe(true)
		expect(editor.props('emojiAutocomplete')).toBe(false)
		expect(editor.props('placeholder')).toBe('Write a message…')
	})

	it('enables the icon send button only for non-empty text and emits the trimmed message', async () => {
		const wrapper = mountComposer()
		const sendButton = wrapper.get('button[aria-label="Send message"]')
		expect(sendButton.attributes('disabled')).toBeDefined()

		await wrapper.get('textarea').setValue('  Hello from Smart Picker  ')
		expect(sendButton.attributes('disabled')).toBeUndefined()
		await wrapper.get('form').trigger('submit')

		expect(wrapper.emitted('send')).toEqual([['Hello from Smart Picker', []]])
		expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('')
	})

	it('lists every active room member when the mention query is empty and includes their matrix id when sending', async () => {
		const wrapper = mountComposer()
		const editor = wrapper.getComponent(NcRichContenteditableStub)

		const autoComplete = editor.props('autoComplete') as (search: string, cb: (items: unknown[]) => void) => void
		const callback = vi.fn()
		autoComplete('', callback)

		expect(callback).toHaveBeenCalledWith([
			{ id: 'ct_anna:test', label: 'Anna', icon: 'icon-user', iconUrl: '/nextcloud/apps/churchtools_chat/api/avatar?mxc=mxc%3A%2F%2Fchat.church.tools%2Fanna-avatar', source: 'users' },
			{ id: 'ct_bob:test', label: 'Bob', icon: 'icon-user', iconUrl: null, source: 'users' },
		])

		await wrapper.get('textarea').setValue('Hi @ct_anna:test how are you')
		await wrapper.get('form').trigger('submit')

		expect(wrapper.emitted('send')).toEqual([['Hi @ct_anna:test how are you', ['@ct_anna:test']]])
	})

	it('filters the room member list locally as more of the mention is typed', () => {
		const wrapper = mountComposer()
		const editor = wrapper.getComponent(NcRichContenteditableStub)
		const autoComplete = editor.props('autoComplete') as (search: string, cb: (items: unknown[]) => void) => void
		const callback = vi.fn()

		autoComplete('an', callback)

		expect(callback).toHaveBeenCalledWith([{ id: 'ct_anna:test', label: 'Anna', icon: 'icon-user', iconUrl: '/nextcloud/apps/churchtools_chat/api/avatar?mxc=mxc%3A%2F%2Fchat.church.tools%2Fanna-avatar', source: 'users' }])
	})

	it('excludes people who are not active (joined) members of the current room', () => {
		const wrapper = mountComposer()
		const editor = wrapper.getComponent(NcRichContenteditableStub)
		const autoComplete = editor.props('autoComplete') as (search: string, cb: (items: unknown[]) => void) => void
		const callback = vi.fn()

		autoComplete('carla', callback)

		expect(callback).toHaveBeenCalledWith([])
	})

	it('does not list mentions when the room has no known members yet', () => {
		const wrapper = mountComposer(false, [], null)
		const editor = wrapper.getComponent(NcRichContenteditableStub)
		const autoComplete = editor.props('autoComplete') as (search: string, cb: (items: unknown[]) => void) => void
		const callback = vi.fn()

		autoComplete('', callback)

		expect(callback).toHaveBeenCalledWith([])
	})

	it('emits typing while content is present and stops when it is cleared', async () => {
		const wrapper = mountComposer()

		await wrapper.get('textarea').setValue('hi')
		expect(wrapper.emitted('typing')?.flat()).toContain(true)

		await wrapper.get('textarea').setValue('')
		expect(wrapper.emitted('typing')?.flat()).toContain(false)
	})

	it('clears typing when sending a message', async () => {
		const wrapper = mountComposer()

		await wrapper.get('textarea').setValue('hello')
		await wrapper.get('form').trigger('submit')

		const typingCalls = wrapper.emitted('typing')?.flat()
		expect(typingCalls).toContain(true)
		expect(typingCalls).toContain(false)
		expect(typingCalls!.indexOf(false)).toBeGreaterThan(typingCalls!.indexOf(true))
	})

	it('appends a picked emoji to the draft', async () => {
		const wrapper = mountComposer()

		await wrapper.findComponent(NcEmojiPickerStub).vm.$emit('select', '👍')

		expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('👍')
	})

	it('enables the send button with only pending files and emits sendFiles without emitting send', async () => {
		const file = new File(['content'], 'notes.pdf', { type: 'application/pdf' })
		const wrapper = mountComposer(false, [file])
		const sendButton = wrapper.get('button[aria-label="Send message"]')
		expect(sendButton.attributes('disabled')).toBeUndefined()

		await wrapper.get('form').trigger('submit')

		expect(wrapper.emitted('sendFiles')).toEqual([[[file]]])
		expect(wrapper.emitted('send')).toBeUndefined()
	})

	it('emits both send and sendFiles when text and pending files are both present', async () => {
		const file = new File(['content'], 'notes.pdf', { type: 'application/pdf' })
		const wrapper = mountComposer(false, [file])

		await wrapper.get('textarea').setValue('hello')
		await wrapper.get('form').trigger('submit')

		expect(wrapper.emitted('sendFiles')).toEqual([[[file]]])
		expect(wrapper.emitted('send')).toEqual([['hello', []]])
	})

	it('renders staged attachments and emits removePendingFile when a chip is removed', async () => {
		const file = new File(['content'], 'notes.pdf', { type: 'application/pdf' })
		const wrapper = mountComposer(false, [file])

		expect(wrapper.text()).toContain('notes.pdf')
		await wrapper.get('.pending-attachment__remove').trigger('click')

		expect(wrapper.emitted('removePendingFile')).toEqual([[file]])
	})
})
