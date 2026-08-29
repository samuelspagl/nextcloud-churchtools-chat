import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path: string) => path,
}))

import { getErrorCode, getErrorMessage, getErrorValue, getGroupContext, getRoomDetails, searchConversations, searchPersons, searchRoomMessages, sendAttachment, startDirectChat } from '../../src/services/chatApi'

const mockedAxios = vi.mocked(axios)

describe('direct chat API', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('sends the person name query to the scoped search endpoint', async () => {
		mockedAxios.get.mockResolvedValue({ data: { data: { persons: [] } } })

		await searchPersons('Anna Schmidt')

		expect(mockedAxios.get).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/persons',
			{ params: { query: 'Anna Schmidt' } },
		)
	})

	it('starts a direct chat by authoritative ChurchTools person id', async () => {
		mockedAxios.post.mockResolvedValue({
			data: {
				data: {
					roomId: '!room:chat.church.tools',
					created: true,
					matrixUserId: '@ct_guid:chat.church.tools',
					displayName: 'Anna Schmidt',
					canChat: false,
				},
			},
		})

		await startDirectChat(42)

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/direct-chats',
			{ personId: 42 },
		)
	})

	it('loads encoded room details from the scoped endpoint', async () => {
		mockedAxios.get.mockResolvedValue({ data: { data: { roomId: '!room:chat.church.tools' } } })

		await getRoomDetails('!room:chat.church.tools')

		expect(mockedAxios.get).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/rooms/!room%3Achat.church.tools/details',
		)
	})

	it('loads group context from the encoded room endpoint', async () => {
		mockedAxios.get.mockResolvedValue({ data: { data: { matchStatus: 'none', group: null, nextcloud: { status: 'unavailable', teams: [] } } } })

		await getGroupContext('!room:chat.church.tools')

		expect(mockedAxios.get).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/rooms/!room%3Achat.church.tools/group-context',
		)
	})

	it('searches messages only in the encoded active room', async () => {
		mockedAxios.get.mockResolvedValue({ data: { data: { events: [] } } })

		await searchRoomMessages('!room:chat.church.tools', 'meeting notes')

		expect(mockedAxios.get).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/rooms/!room%3Achat.church.tools/search',
			{ params: { query: 'meeting notes' } },
		)
	})

	it('searches all accessible conversations through the global endpoint', async () => {
		mockedAxios.get.mockResolvedValue({ data: { data: { results: [] } } })

		await searchConversations('meeting notes')

		expect(mockedAxios.get).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/search',
			{ params: { query: 'meeting notes' } },
		)
	})
})

describe('attachment API', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('uploads the file as multipart form data to the scoped endpoint', async () => {
		mockedAxios.post.mockResolvedValue({
			data: {
				data: {
					eventId: '$event',
					transactionId: 'txn-1',
					attachment: { kind: 'file', mxcUrl: 'mxc://server/media', filename: 'notes.pdf', mimeType: 'application/pdf', size: 1234 },
				},
			},
		})
		const file = new File(['content'], 'notes.pdf', { type: 'application/pdf' })

		await sendAttachment('!room:chat.church.tools', file, 'txn-1')

		expect(mockedAxios.post).toHaveBeenCalledWith(
			'/apps/churchtools_chat/api/rooms/!room%3Achat.church.tools/attachments',
			expect.any(FormData),
		)
		const form = mockedAxios.post.mock.calls[0][1] as FormData
		expect(form.get('file')).toBe(file)
		expect(form.get('transactionId')).toBe('txn-1')
	})
})

describe('error envelope helpers', () => {
	it('reads the code, message and value from the response envelope', () => {
		const error = {
			response: {
				data: {
					error: {
						code: 'matrix_rate_limited',
						message: 'rate limited',
						value: 12,
					},
				},
			},
		}

		expect(getErrorCode(error)).toBe('matrix_rate_limited')
		expect(getErrorMessage(error)).toBe('rate limited')
		expect(getErrorValue(error)).toBe(12)
	})

	it('returns null or a fallback when no envelope is present', () => {
		const plain = new Error('boom')

		expect(getErrorCode(plain)).toBeNull()
		expect(getErrorValue(plain)).toBeNull()
		expect(getErrorMessage(plain)).toBe('The request could not be completed.')
	})
})
