// @vitest-environment happy-dom

import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MessageReferencePreview from '../../src/components/MessageReferencePreview.vue'
import MessageReferencePreviewControls from '../../src/components/MessageReferencePreviewControls.vue'
import { extractReferencePreview } from '../../src/services/referenceApi'
import type { MessageReferencePreviewContext } from '../../src/components/messageReferencePreviewContext'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

vi.mock('../../src/services/referenceApi', () => ({
	containsHttpLink: (text: string) => /https?:\/\//.test(text),
	extractReferencePreview: vi.fn(),
}))

const mockedExtract = vi.mocked(extractReferencePreview)

const reference = {
	richObjectType: 'deck-card',
	richObject: { card: { id: 6 } },
	openGraphObject: {
		id: 'https://cloud.example.test/apps/deck/card/6',
		name: 'Test card',
		description: null,
		thumb: null,
		link: 'https://cloud.example.test/apps/deck/card/6',
	},
	accessible: true,
}

const NcButtonStub = defineComponent({
	inheritAttrs: false,
	emits: ['click'],
	template: '<button v-bind="$attrs" type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
})

const NcReferenceWidgetStub = defineComponent({
	props: {
		reference: { type: Object, required: true },
		interactive: { type: Boolean, default: false },
	},
	template: '<div class="reference-widget-stub"><div class="widget-custom" :class="{ \'full-width\': reference.richObjectType === \'deck-board\' }">{{ reference.openGraphObject.name }}</div></div>',
})

function mountPreview(text = 'See https://cloud.example.test/apps/deck/card/6', messageId = '$message') {
	return mount(MessageReferencePreview, {
		props: { messageId, text, isOwn: false },
		slots: {
			default: ({ preview }: { preview: MessageReferencePreviewContext }) => [
				h('div', { class: 'message-stub' }, 'Message'),
				h(MessageReferencePreviewControls, { preview }),
			],
		},
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcIconSvgWrapper: true,
				NcLoadingIcon: true,
				NcReferenceWidget: NcReferenceWidgetStub,
			},
		},
	})
}

describe('MessageReferencePreview', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mockedExtract.mockResolvedValue(reference)
	})

	it('does not resolve or render messages without links', async () => {
		const wrapper = mountPreview('No links here')
		await flushPromises()

		expect(mockedExtract).not.toHaveBeenCalled()
		expect(wrapper.find('.reference-preview__controls').exists()).toBe(false)
		expect(wrapper.find('.reference-preview__area').exists()).toBe(false)
		expect(wrapper.text()).toContain('Message')
	})

	it('renders the resolved widget below the message with interactive mode enabled', async () => {
		const wrapper = mountPreview()
		await flushPromises()

		expect(mockedExtract).toHaveBeenCalledTimes(1)
		expect(wrapper.text()).toContain('Test card')
		expect(wrapper.getComponent(NcReferenceWidgetStub).props('interactive')).toBe(true)
		expect(wrapper.get('.reference-preview__area').attributes('aria-label')).toBe('Link preview')
	})

	it('starts expanded and can be collapsed and reopened', async () => {
		const wrapper = mountPreview()
		await flushPromises()

		await wrapper.get('button[aria-label="Hide link preview"]').trigger('click')
		expect(wrapper.findComponent(NcReferenceWidgetStub).exists()).toBe(false)
		expect(wrapper.get('button[aria-label="Show link preview"]').attributes('aria-expanded')).toBe('false')

		await wrapper.get('button[aria-expanded="false"]').trigger('click')
		expect(wrapper.findComponent(NcReferenceWidgetStub).exists()).toBe(true)
		expect(mockedExtract).toHaveBeenCalledTimes(1)
	})

	it('switches independently between compact and full width', async () => {
		const wrapper = mountPreview()
		await flushPromises()
		const frame = wrapper.get('.reference-preview__frame')

		expect(frame.classes()).not.toContain('reference-preview__frame--full')
		await wrapper.get('button[aria-label="Use full preview width"]').trigger('click')
		expect(frame.classes()).toContain('reference-preview__frame--full')
		await wrapper.get('button[aria-label="Use compact preview width"]').trigger('click')
		expect(frame.classes()).not.toContain('reference-preview__frame--full')
	})

	it('uses the provider full-width preference as its initial mode', async () => {
		mockedExtract.mockResolvedValueOnce({ ...reference, richObjectType: 'deck-board' })
		const wrapper = mountPreview('Board https://cloud.example.test/apps/deck/board/1', '$board')
		await flushPromises()

		expect(wrapper.get('.reference-preview__frame').classes()).toContain('reference-preview__frame--full')
		expect(wrapper.get('button[aria-label="Use compact preview width"]').attributes('aria-pressed')).toBe('true')
	})

	it('silently omits failed or inaccessible references', async () => {
		mockedExtract.mockRejectedValueOnce(new Error('Reference API unavailable'))
		const failed = mountPreview('Failed https://example.test/failure', '$failed')
		await flushPromises()
		expect(failed.find('.reference-preview__controls').exists()).toBe(false)
		expect(failed.find('.reference-preview__area').exists()).toBe(false)

		mockedExtract.mockResolvedValueOnce(null)
		const inaccessible = mountPreview('Private https://example.test/private', '$private')
		await flushPromises()
		expect(inaccessible.find('.reference-preview__controls').exists()).toBe(false)
		expect(inaccessible.find('.reference-preview__area').exists()).toBe(false)
	})

	it('resets local display state when the message changes', async () => {
		const wrapper = mountPreview()
		await flushPromises()
		await wrapper.get('button[aria-label="Hide link preview"]').trigger('click')

		await wrapper.setProps({
			messageId: '$next',
			text: 'Next https://cloud.example.test/apps/deck/card/9',
		})
		await flushPromises()

		expect(wrapper.findComponent(NcReferenceWidgetStub).exists()).toBe(true)
		expect(wrapper.get('button[aria-label="Hide link preview"]').attributes('aria-expanded')).toBe('true')
	})

	it('keeps the visibility state independent for each message', async () => {
		const Host = defineComponent({
			components: { MessageReferencePreview, MessageReferencePreviewControls },
			template: `
				<div>
					<MessageReferencePreview v-slot="{ preview }" message-id="$one" text="First https://example.test/one" :is-own="false">
						<MessageReferencePreviewControls :preview="preview" />
					</MessageReferencePreview>
					<MessageReferencePreview v-slot="{ preview }" message-id="$two" text="Second https://example.test/two" :is-own="true">
						<MessageReferencePreviewControls :preview="preview" />
					</MessageReferencePreview>
				</div>
			`,
		})
		const wrapper = mount(Host, {
			global: {
				stubs: {
					NcButton: NcButtonStub,
					NcIconSvgWrapper: true,
					NcLoadingIcon: true,
					NcReferenceWidget: NcReferenceWidgetStub,
				},
			},
		})
		await flushPromises()

		const hideButtons = wrapper.findAll('button[aria-label="Hide link preview"]')
		expect(hideButtons).toHaveLength(2)
		await hideButtons[0].trigger('click')

		expect(wrapper.findAll('button[aria-label="Hide link preview"]')).toHaveLength(1)
		expect(wrapper.findAll('button[aria-expanded="false"]')).toHaveLength(1)
	})

	it('renders the preview actions as icon-only controls', async () => {
		const received = mountPreview()
		await flushPromises()
		expect(received.get('.reference-preview-controls').findAll('button')).toHaveLength(2)
		expect(received.text()).not.toContain('Show link preview')
		expect(received.text()).not.toContain('Hide link preview')

		const own = mount(MessageReferencePreview, {
			props: {
				messageId: '$own',
				text: 'Own https://example.test/own',
				isOwn: true,
			},
			slots: {
				default: ({ preview }: { preview: MessageReferencePreviewContext }) => [
					h('div', { class: 'message-stub' }, 'Own message'),
					h(MessageReferencePreviewControls, { preview }),
				],
			},
			global: {
				stubs: {
					NcButton: NcButtonStub,
					NcIconSvgWrapper: true,
					NcLoadingIcon: true,
					NcReferenceWidget: NcReferenceWidgetStub,
				},
			},
		})
		await flushPromises()
		expect(own.get('.reference-preview').classes()).toContain('reference-preview--own')
	})
})
