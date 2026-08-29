// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import MessageTimeline from '../../src/components/MessageTimeline.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

class ResizeObserverStub {
	observe() {}
	disconnect() {}
}

const MessageBubbleStub = defineComponent({ template: '<div />' })
const NcLoadingIconStub = defineComponent({ template: '<span class="loading-icon" />' })

function mountTimeline(loading = false) {
	vi.stubGlobal('ResizeObserver', ResizeObserverStub)
	return mount(MessageTimeline, {
		props: {
			messages: [],
			currentUserId: '@user:chat.church.tools',
			loading,
			hasMore: true,
			focusMessageId: null,
			replyTargets: {},
		},
		global: {
			stubs: {
				MessageBubble: MessageBubbleStub,
				NcLoadingIcon: NcLoadingIconStub,
			},
		},
	})
}

afterEach(() => {
	vi.unstubAllGlobals()
})

describe('MessageTimeline', () => {
	it('places the older-message action in its centered row and emits pagination requests', async () => {
		const wrapper = mountTimeline()
		const row = wrapper.get('.timeline__load-older-row')
		const button = row.get('button.timeline__load-older')

		expect(button.text()).toBe('Load older messages')
		await button.trigger('click')
		expect(wrapper.emitted('loadOlder')).toHaveLength(1)
	})

	it('disables the older-message action and shows progress while loading', () => {
		const wrapper = mountTimeline(true)
		const button = wrapper.get('button.timeline__load-older')

		expect(button.attributes('disabled')).toBeDefined()
		expect(button.find('.loading-icon').exists()).toBe(true)
	})
})
