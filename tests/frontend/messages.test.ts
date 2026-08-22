import { describe, expect, it } from 'vitest'
import type { ChatMessage } from '../../src/types/chat'
import { messageSenderLabel } from '../../src/utils/messages'

const message: ChatMessage = {
	id: '$event',
	sender: '@ct_guid:chat.church.tools',
	senderName: 'Anna Schmidt',
	body: 'Hello',
	timestamp: 1,
}

describe('messageSenderLabel', () => {
	it('uses the room-local resolved sender name', () => {
		expect(messageSenderLabel(message, '@someone-else:test', 'You')).toBe('Anna Schmidt')
	})

	it('labels own messages without exposing the Matrix id', () => {
		expect(messageSenderLabel(message, message.sender, 'You')).toBe('You')
	})

	it('keeps the Matrix id only as the technical fallback', () => {
		expect(messageSenderLabel({ ...message, senderName: '' }, '@someone-else:test', 'You')).toBe(message.sender)
	})
})
