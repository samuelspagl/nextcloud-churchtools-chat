import { describe, expect, it } from 'vitest'
import type { ChatRoom } from '../../src/types/chat'
import { mergeRooms } from '../../src/utils/rooms'

function room(id: string, timestamp: number, events: ChatRoom['events'] = []) : ChatRoom {
	return {
		id,
		name: id,
		avatarUrl: null,
		encrypted: false,
		kind: 'group',
		memberCount: 1,
		unreadCount: 0,
		lastMessage: timestamp ? { id: `event-${timestamp}`, sender: '@u:test', body: 'message', timestamp } : null,
		events,
	}
}

describe('mergeRooms', () => {
	it('merges events by stable event id and sorts rooms by activity', () => {
		const first = room('!first:test', 10, [{ id: '$one', sender: '@u:test', body: 'old', timestamp: 10 }])
		const update = room('!first:test', 30, [
			{ id: '$one', sender: '@u:test', body: 'updated', timestamp: 10 },
			{ id: '$two', sender: '@u:test', body: 'new', timestamp: 30 },
		])
		const second = room('!second:test', 20)

		const result = mergeRooms([first, second], [update])

		expect(result.map(({ id }) => id)).toEqual(['!first:test', '!second:test'])
		expect(result[0].events).toHaveLength(2)
		expect(result[0].events[0].body).toBe('updated')
	})

	it('does not replace a resolved name with an incremental fallback', () => {
		const existing = { ...room('!first:test', 10), name: 'Anna Schmidt', avatarUrl: 'https://example.test/anna.jpg' }
		const update = { ...room('!first:test', 20), name: 'Conversation' }

		const [result] = mergeRooms([existing], [update])

		expect(result.name).toBe('Anna Schmidt')
		expect(result.avatarUrl).toBe('https://example.test/anna.jpg')
	})
})
