import { describe, expect, it } from 'vitest'
import type { ChatMessage } from '../../src/types/chat'
import { buildAnnotationRelation, buildReplyRelation, buildReplaceRelation, getReactionKeys, getReplyTargetId } from '../../src/utils/relations'

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
