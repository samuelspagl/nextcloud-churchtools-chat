// @vitest-environment happy-dom

import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ReplyPreview from '../../src/components/ReplyPreview.vue'
import type { ChatMessage } from '../../src/types/chat'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

const target: ChatMessage = {
	id: '$original',
	sender: '@ct_anna:test',
	senderName: 'Anna Schmidt',
	body: 'Original message text',
	timestamp: 100,
}

describe('ReplyPreview', () => {
	function mountPreview(props: Partial<{ message: ChatMessage | null; currentUserId: string; canJump: boolean }> = {}) {
		return shallowMount(ReplyPreview, {
			props: {
				message: target,
				currentUserId: '@me:test',
				...props,
			},
		})
	}

	it('renders the original sender and message text', () => {
		const wrapper = mountPreview()

		expect(wrapper.get('.reply-preview__sender').text()).toBe('Anna Schmidt')
		expect(wrapper.get('.reply-preview__text').text()).toBe('Original message text')
	})

	it('truncates long message bodies', () => {
		const wrapper = mountPreview({ message: { ...target, body: 'x'.repeat(300) } })

		expect(wrapper.get('.reply-preview__text').text()).toMatch(/^x{160}…$/)
	})

	it('shows a deleted placeholder for redacted originals', () => {
		const wrapper = mountPreview({ message: { ...target, redacted: true, body: '' } })

		expect(wrapper.get('.reply-preview__text').text()).toBe('Message deleted')
	})

	it('shows a placeholder when the original is unavailable', () => {
		const wrapper = mountPreview({ message: null })

		expect(wrapper.get('.reply-preview__text').text()).toBe('Original message unavailable')
	})

	it('emits jump on click when jumpable', async () => {
		const wrapper = mountPreview({ canJump: true })

		await wrapper.get('button').trigger('click')

		expect(wrapper.emitted('jump')).toHaveLength(1)
	})

	it('is not interactive when jump is not available', () => {
		const wrapper = mountPreview({ canJump: false })

		expect(wrapper.find('button').exists()).toBe(false)
	})
})