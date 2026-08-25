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
	function mountPreview(props: {
		message?: ChatMessage | null
		fallbackText?: string | null
		currentUserId?: string
		canJump?: boolean
	} = {}) {
		return shallowMount(ReplyPreview, {
			props: {
				message: target,
				currentUserId: '@me:test',
				...props,
			},
		})
	}

	it('renders the original sender and a block quote with the message text', () => {
		const wrapper = mountPreview()

		expect(wrapper.get('.reply-preview__sender').text()).toBe('Anna Schmidt')
		expect(wrapper.get('.reply-preview__quote').text()).toBe('Original message text')
		expect(wrapper.find('blockquote.reply-preview__quote').exists()).toBe(true)
	})

	it('shows a deleted placeholder for redacted originals', () => {
		const wrapper = mountPreview({ message: { ...target, redacted: true, body: '' } })

		expect(wrapper.get('.reply-preview__quote').text()).toBe('Message deleted')
	})

	it('shows the fallback quote when the original is unavailable', () => {
		const wrapper = mountPreview({ message: null, fallbackText: 'quoted original lines' })

		expect(wrapper.get('.reply-preview__quote').text()).toBe('quoted original lines')
	})

	it('shows a placeholder when neither original nor fallback is available', () => {
		const wrapper = mountPreview({ message: null, fallbackText: null })

		expect(wrapper.get('.reply-preview__quote').text()).toBe('Replying to a message')
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