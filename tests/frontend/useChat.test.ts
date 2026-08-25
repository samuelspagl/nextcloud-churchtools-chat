import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
	sendMessage: vi.fn(),
	getMessages: vi.fn(),
	getEvent: vi.fn(),
	setFullyRead: vi.fn(),
	setTyping: vi.fn(),
	reactToMessage: vi.fn(),
	deleteMessage: vi.fn(),
}))

vi.mock('../../src/services/chatApi', async (importActual) => {
	const actual = await importActual<typeof import('../../src/services/chatApi')>()
	return {
		...actual,
		sendMessage: mocks.sendMessage,
		getMessages: mocks.getMessages,
		getEvent: mocks.getEvent,
		setFullyRead: mocks.setFullyRead,
		setTyping: mocks.setTyping,
		reactToMessage: mocks.reactToMessage,
		deleteMessage: mocks.deleteMessage,
	}
})

import { useChat } from '../../src/composables/useChat'

describe('useChat send/retry', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		mocks.getMessages.mockResolvedValue({ events: [] })
		mocks.setFullyRead.mockResolvedValue(undefined)
	})

	it('reuses the same transaction id when retrying a failed message', async () => {
		mocks.sendMessage.mockRejectedValueOnce(new Error('transient'))
		const chat = useChat()
		await chat.selectRoom('!room:test')
		chat.rooms.value = [{
			id: '!room:test',
			name: 'r',
			avatarUrl: null,
			encrypted: false,
			kind: 'group',
			memberCount: 1,
			unreadCount: 0,
			lastMessage: null,
			events: [],
		}]

		try {
			await chat.send('hello')
		} catch {
			// expected transient failure
		}

		const failed = chat.rooms.value[0].events.find((m) => m.status === 'failed')
		expect(failed).toBeTruthy()
		const txn = failed!.transactionId
		mocks.sendMessage.mockResolvedValueOnce({ eventId: '$ok', transactionId: txn })

		await chat.retry(failed!)

		expect(mocks.sendMessage).toHaveBeenCalledTimes(2)
		expect(mocks.sendMessage.mock.calls[1][2]).toBe(txn)
	})

	it('fetches reply targets that are not loaded in the timeline', async () => {
		mocks.getEvent.mockResolvedValue({ id: '$original', sender: '@anna:test', senderName: 'Anna', body: 'Original', timestamp: 100 })
		mocks.getMessages.mockResolvedValue({
			events: [{
				id: '$reply',
				sender: '@ben:test',
				body: 'My reply',
				timestamp: 200,
				relatesTo: { 'm.in_reply_to': { event_id: '$original' } },
			}],
		})
		const chat = useChat()
		chat.rooms.value = [{
			id: '!room:test',
			name: 'r',
			avatarUrl: null,
			encrypted: false,
			kind: 'group',
			memberCount: 1,
			unreadCount: 0,
			lastMessage: null,
			events: [],
		}]

		await chat.selectRoom('!room:test')

		await vi.waitFor(() => {
			expect(mocks.getEvent).toHaveBeenCalledWith('!room:test', '$original')
			expect(chat.replyTargets.value['$original']?.body).toBe('Original')
		})
	})

	it('forwards typing state to the active room', async () => {
		mocks.setTyping.mockResolvedValue(undefined)
		const chat = useChat()
		chat.rooms.value = [{
			id: '!room:test',
			name: 'r',
			avatarUrl: null,
			encrypted: false,
			kind: 'direct',
			memberCount: 1,
			unreadCount: 0,
			lastMessage: null,
			events: [],
		}]
		await chat.selectRoom('!room:test')

		await chat.setTyping(true)

		expect(mocks.setTyping).toHaveBeenCalledWith('!room:test', true)
	})

	it('ignores typing requests without an active room', async () => {
		const chat = useChat()
		await chat.setTyping(true)

		expect(mocks.setTyping).not.toHaveBeenCalled()
	})

	it('tracks the reaction event id when reacting', async () => {
		mocks.reactToMessage.mockResolvedValue({ eventId: '$reaction', transactionId: 'nc-x' })
		mocks.getMessages.mockResolvedValue({
			events: [{ id: '$msg', sender: '@other:test', body: 'hi', timestamp: 1 }],
		})
		const chat = useChat()
		chat.rooms.value = [{
			id: '!room:test',
			name: 'r',
			avatarUrl: null,
			encrypted: false,
			kind: 'group',
			memberCount: 1,
			unreadCount: 0,
			lastMessage: null,
			events: [],
		}]
		await chat.selectRoom('!room:test')

		await chat.react(chat.rooms.value[0].events[0], '👍')

		const message = chat.rooms.value[0].events[0]
		expect(message.reactions).toEqual({ '👍': 1 })
		expect(message.ownReactions).toEqual([{ key: '👍', eventId: '$reaction' }])
	})

	it('removes an own reaction by redacting its event id', async () => {
		mocks.deleteMessage.mockResolvedValue(undefined)
		mocks.getMessages.mockResolvedValue({
			events: [{
				id: '$msg',
				sender: '@me:test',
				body: 'hi',
				timestamp: 1,
				reactions: { '👍': 2 },
				ownReactions: [{ key: '👍', eventId: '$reaction' }],
			}],
		})
		const chat = useChat()
		chat.rooms.value = [{
			id: '!room:test',
			name: 'r',
			avatarUrl: null,
			encrypted: false,
			kind: 'group',
			memberCount: 1,
			unreadCount: 0,
			lastMessage: null,
			events: [],
		}]
		await chat.selectRoom('!room:test')

		await chat.unreact(chat.rooms.value[0].events[0], '👍')

		expect(mocks.deleteMessage).toHaveBeenCalledWith('!room:test', '$reaction')
		const message = chat.rooms.value[0].events[0]
		expect(message.reactions).toEqual({ '👍': 1 })
		expect(message.ownReactions).toEqual([])
	})
})
