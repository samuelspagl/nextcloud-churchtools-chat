import { describe, expect, it } from 'vitest'
import type { ChatMessage } from '../../src/types/chat'
import { lastReadOwnMessageId, otherReadEventId, readReceiptIndex } from '../../src/utils/readReceipts'

function message(id: string, sender: string): ChatMessage {
	return { id, sender, body: 'x', timestamp: 1 }
}

describe('readReceipts helpers', () => {
	it('returns the first receipt from another user', () => {
		expect(otherReadEventId({ '@a:test': '$1' }, '@me:test')).toBe('$1')
		expect(otherReadEventId({ '@me:test': '$1' }, '@me:test')).toBeNull()
		expect(otherReadEventId(undefined, '@me:test')).toBeNull()
	})

	it('finds the receipt event index in the loaded list', () => {
		const events = [message('$1', '@me:test'), message('$2', '@me:test'), message('$3', '@a:test')]
		expect(readReceiptIndex(events, '$2')).toBe(1)
		expect(readReceiptIndex(events, null)).toBeNull()
		expect(readReceiptIndex(events, '$unknown')).toBeNull()
	})

	it('returns the last own message before the read receipt', () => {
		const events = [
			message('$1', '@me:test'),
			message('$2', '@a:test'),
			message('$3', '@me:test'),
			message('$4', '@me:test'),
			message('$5', '@a:test'),
		]
		expect(lastReadOwnMessageId(events, { '@a:test': '$4' }, '@me:test')).toBe('$4')
		expect(lastReadOwnMessageId(events, { '@a:test': '$5' }, '@me:test')).toBe('$4')
		expect(lastReadOwnMessageId(events, { '@a:test': '$1' }, '@me:test')).toBe('$1')
		expect(lastReadOwnMessageId(events, undefined, '@me:test')).toBeNull()
		expect(lastReadOwnMessageId(events, { '@a:test': '$unknown' }, '@me:test')).toBeNull()
	})
})