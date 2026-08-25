import { describe, expect, it } from 'vitest'
import type { ChatMessage } from '../../src/types/chat'
import { buildAnnotationRelation, buildReplyRelation, buildReplaceRelation, getReactionKeys, getReplyFallbackQuote, getReplyTargetId, isReplyMessage } from '../../src/utils/relations'

function message(overrides: Partial<ChatMessage> = {}): ChatMessage {
	return {
		id: '$m',
		sender: '@u:test',
		body: 'hi',
		timestamp: 1,
		...overrides,
	}
}

describe('relations helpers', () => {
	it('extracts the reply target from relatesTo', () => {
		expect(getReplyTargetId(message({ relatesTo: { 'm.in_reply_to': { event_id: '$target' } } }))).toBe('$target')
		expect(getReplyTargetId(message())).toBeNull()
	})

	it('extracts the reply target from a thread relation', () => {
		expect(getReplyTargetId(message({ relatesTo: { rel_type: 'm.thread', event_id: '$root', 'm.in_reply_to': { event_id: '$target' } } }))).toBe('$target')
		expect(getReplyTargetId(message({ relatesTo: { rel_type: 'm.thread', event_id: '$root' } }))).toBe('$root')
	})

	it('detects replies from the rich-reply fallback in the body', () => {
		expect(isReplyMessage(message({ body: '> <@other:test> quoted text\n\nmy reply' }))).toBe(true)
		expect(isReplyMessage(message({ body: '> <@other:test> quoted text\n\nmy reply', relatesTo: { 'm.in_reply_to': { event_id: '$t' } } }))).toBe(true)
		expect(isReplyMessage(message({ body: 'a normal message' }))).toBe(false)
	})

	it('extracts the quoted text from a fallback but not for a relation reply', () => {
		const fallback = message({ body: '> <@other:test> quoted line\n> second quoted\n\nmy reply' })
		expect(getReplyFallbackQuote(fallback)).toBe('quoted line\nsecond quoted')
		expect(getReplyFallbackQuote(message({ body: '> quoted', relatesTo: { 'm.in_reply_to': { event_id: '$t' } } }))).toBeNull()
	})

	it('builds reply, annotation and replace relations', () => {
		expect(buildReplyRelation('$t')).toEqual({ 'm.in_reply_to': { event_id: '$t' } })
		expect(buildAnnotationRelation('$t', '👍')).toEqual({ rel_type: 'm.annotation', event_id: '$t', key: '👍' })
		expect(buildReplaceRelation('$t')).toEqual({ rel_type: 'm.replace', event_id: '$t' })
	})

	it('lists reaction keys', () => {
		expect(getReactionKeys(message({ reactions: { '👍': 1, '❤️': 2 } }))).toEqual(['👍', '❤️'])
		expect(getReactionKeys(message())).toEqual([])
	})
})
