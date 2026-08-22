// @vitest-environment happy-dom

import { shallowMount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import MessageBubble from '../../src/components/MessageBubble.vue'

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
	function mountMessageBubble() {
		return shallowMount(MessageBubble, {
			props: {
				currentUserId: '@me:example.test',
				message: {
					id: '$message',
					sender: '@me:example.test',
					body: 'See https://cloud.example.test/apps/deck/card/6',
					timestamp: 1_700_000_000_000,
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
})
