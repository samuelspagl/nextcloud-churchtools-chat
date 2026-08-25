import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
	sendMessage: vi.fn(),
	getMessages: vi.fn(),
	getEvent: vi.fn(),
	setFullyRead: vi.fn(),
}))

vi.mock('../../src/services/chatApi', async (importActual) => {
	const actual = await importActual<typeof import('../../src/services/chatApi')>()
	return { ...actual, sendMessage: mocks.sendMessage, getMessages: mocks.getMessages, getEvent: mocks.getEvent, setFullyRead: mocks.setFullyRead }
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
})
