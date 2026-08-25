// @vitest-environment happy-dom

import { shallowMount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import MessageBubble from '../../src/components/MessageBubble.vue'
import ReplyPreview from '../../src/components/ReplyPreview.vue'

const mocks = vi.hoisted(() => {
	const pick = vi.fn()
	const builder = {
		setMultiSelect: vi.fn(),
		allowDirectories: vi.fn(),
		setCanPick: vi.fn(),
		setType: vi.fn(),
		build: vi.fn(() => ({ pick })),
	}
	builder.setMultiSelect.mockReturnValue(builder)
	builder.allowDirectories.mockReturnValue(builder)
	builder.setCanPick.mockReturnValue(builder)
	builder.setType.mockReturnValue(builder)
	const FilePickerClosed = class FilePickerClosed extends Error {}

	return {
		pick,
		builder,
		FilePickerClosed,
		getFilePickerBuilder: vi.fn(() => builder),
		saveAttachment: vi.fn(),
	}
})

vi.mock('@nextcloud/dialogs', () => ({
	FilePickerClosed: mocks.FilePickerClosed,
	getFilePickerBuilder: mocks.getFilePickerBuilder,
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('../../src/services/chatApi', () => ({
	getErrorMessage: vi.fn(),
	saveAttachment: mocks.saveAttachment,
}))

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

const MessageReferencePreviewStub = defineComponent({
	props: {
		messageId: { type: String, required: true },
		text: { type: String, required: true },
		isOwn: { type: Boolean, required: true },
	},
	setup() {
		return { preview: {} }
	},
	template: '<div class="reference-preview-stub"><div class="message-slot"><slot :preview="preview" /></div><div class="widget-stub">Preview</div></div>',
})

describe('MessageBubble', () => {
	function mountMessageBubble(withAttachment = false) {
		return shallowMount(MessageBubble, {
			props: {
				currentUserId: '@me:example.test',
				message: {
					id: '$message',
					sender: '@me:example.test',
					body: 'See https://cloud.example.test/apps/deck/card/6',
					timestamp: 1_700_000_000_000,
					attachment: withAttachment ? {
						kind: 'image',
						filename: 'photo.jpg',
						mimeType: 'image/jpeg',
						mxcUrl: 'mxc://matrix.example.test/photo',
						size: 123,
					} : undefined,
				},
			},
			global: {
				stubs: {
					MessageReferencePreview: MessageReferencePreviewStub,
					MessageReferencePreviewControls: true,
				},
			},
		})
	}

	it('places the reference preview outside and below the colored text bubble', () => {
		const wrapper = mountMessageBubble()

		const bubble = wrapper.get('.message__bubble')
		const preview = wrapper.get('.widget-stub')
		expect(bubble.find('.reference-preview-stub').exists()).toBe(false)
		expect(preview.element.parentElement?.classList).toContain('reference-preview-stub')
		expect(preview.element.previousElementSibling?.classList).toContain('message-slot')
	})

	it('configures the picker to confirm the current folder before saving an attachment', async () => {
		mocks.pick.mockResolvedValueOnce('/Photos')
		mocks.saveAttachment.mockResolvedValueOnce({ path: 'Photos/photo.jpg' })
		const wrapper = mountMessageBubble(true)

		await wrapper.get('.message__actions').findAll('nc-button-stub')[1].trigger('click')

		expect(mocks.getFilePickerBuilder).toHaveBeenCalledWith('Choose a folder')
		expect(mocks.builder.setMultiSelect).toHaveBeenCalledWith(false)
		expect(mocks.builder.allowDirectories).toHaveBeenCalledWith(true)
		expect(mocks.builder.setCanPick.mock.calls[0]?.[0]({ type: 'folder' })).toBe(true)
		expect(mocks.builder.setCanPick.mock.calls[0]?.[0]({ type: 'file' })).toBe(false)
		expect(mocks.builder.setType).toHaveBeenCalledWith(1)
		expect(mocks.saveAttachment).toHaveBeenCalledWith('mxc://matrix.example.test/photo', 'Photos', 'photo.jpg')
	})

	it('does not save when the folder picker is closed', async () => {
		mocks.saveAttachment.mockClear()
		mocks.pick.mockRejectedValueOnce(new mocks.FilePickerClosed())
		const wrapper = mountMessageBubble(true)

		await wrapper.get('.message__actions').findAll('nc-button-stub')[1].trigger('click')

		expect(mocks.saveAttachment).not.toHaveBeenCalled()
	})

	it('keeps reply and reaction actions in the bubble hover menu', async () => {
		const wrapper = mountMessageBubble()
		const actions = wrapper.get('.message__actions')

		expect(actions.element.parentElement?.classList).toContain('message__bubble-line')
		expect(wrapper.get('.message__bubble').find('.message__actions').exists()).toBe(false)
		const actionButtons = actions.findAll('nc-button-stub')
		expect(actionButtons).toHaveLength(2)

		await actionButtons[0].trigger('click')
		await actionButtons[1].trigger('click')
		expect(wrapper.emitted('reply')).toHaveLength(1)
		expect(wrapper.emitted('react')?.[0]?.[1]).toBe('👍')
	})

	it('renders a reply preview for reply messages and forwards jumps', async () => {
		const wrapper = shallowMount(MessageBubble, {
			props: {
				currentUserId: '@me:example.test',
				message: {
					id: '$reply',
					sender: '@me:example.test',
					body: 'Answer',
					timestamp: 1,
					relatesTo: { 'm.in_reply_to': { event_id: '$original' } },
				},
				replyToMessage: {
					id: '$original',
					sender: '@other:example.test',
					senderName: 'Other',
					body: 'Question',
					timestamp: 1,
				},
				canJumpReply: true,
			},
			global: {
				stubs: {
					MessageReferencePreview: MessageReferencePreviewStub,
					MessageReferencePreviewControls: true,
				},
			},
		})

		const preview = wrapper.findComponent(ReplyPreview)
		expect(preview.exists()).toBe(true)
		expect(preview.props('canJump')).toBe(true)

		await preview.vm.$emit('jump')
		expect(wrapper.emitted('jump')).toHaveLength(1)
	})

	it('does not render a reply preview without a reply relation', () => {
		const wrapper = mountMessageBubble()

		expect(wrapper.findComponent(ReplyPreview).exists()).toBe(false)
	})

	it('renders a reply preview from the body fallback when there is no relation', () => {
		const wrapper = shallowMount(MessageBubble, {
			props: {
				currentUserId: '@me:example.test',
				message: {
					id: '$reply',
					sender: '@me:example.test',
					body: '> <@other:example.test> Question text\n\nAnswer',
					timestamp: 1,
				},
			},
			global: {
				stubs: {
					MessageReferencePreview: MessageReferencePreviewStub,
					MessageReferencePreviewControls: true,
				},
			},
		})

		const preview = wrapper.findComponent(ReplyPreview)
		expect(preview.exists()).toBe(true)
		expect(preview.props('fallbackText')).toBe('Question text')
		expect(preview.props('canJump')).toBe(false)
	})

	it('shows a read check on own sent messages marked as read', () => {
		const wrapper = shallowMount(MessageBubble, {
			props: {
				currentUserId: '@me:example.test',
				message: { id: '$m', sender: '@me:example.test', body: 'hi', timestamp: 1, status: 'sent' },
				readByOther: true,
			},
			global: {
				stubs: {
					MessageReferencePreview: MessageReferencePreviewStub,
					MessageReferencePreviewControls: true,
				},
			},
		})

		expect(wrapper.find('.message__read').exists()).toBe(true)
	})

	it('does not show a read check when the message is not read by the other user', () => {
		const wrapper = shallowMount(MessageBubble, {
			props: {
				currentUserId: '@me:example.test',
				message: { id: '$m', sender: '@me:example.test', body: 'hi', timestamp: 1, status: 'sent' },
			},
			global: {
				stubs: {
					MessageReferencePreview: MessageReferencePreviewStub,
					MessageReferencePreviewControls: true,
				},
			},
		})

		expect(wrapper.find('.message__read').exists()).toBe(false)
	})
})
