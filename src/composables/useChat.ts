import { computed, onBeforeUnmount, onMounted, readonly, ref, shallowRef } from 'vue'
import {
	getErrorMessage,
	getMessages,
	getRoomDetails,
	getRooms,
	getStatus,
	reactToMessage,
	searchConversations as searchConversationsRequest,
	searchPersons as searchPersonsRequest,
	searchRoomMessages as searchRoomMessagesRequest,
	sendMessage,
	startDirectChat as startDirectChatRequest,
	syncRooms,
} from '../services/chatApi'
import type { ChatMessage, ChatRoom, ChatStatus, ConversationSearchResult, PersonSearchResult, RoomDetails } from '../types/chat'
import { mergeRooms } from '../utils/rooms'

function transactionId(): string {
	return `nc-${crypto.randomUUID()}`
}

export function useChat() {
	const status = shallowRef<ChatStatus | null>(null)
	const rooms = ref<ChatRoom[]>([])
	const activeRoomId = shallowRef<string | null>(null)
	const loading = shallowRef(true)
	const loadingMessages = shallowRef(false)
	const detailsOpen = shallowRef(false)
	const roomDetails = shallowRef<RoomDetails | null>(null)
	const loadingRoomDetails = shallowRef(false)
	const roomDetailsError = shallowRef('')
	const error = shallowRef('')
	const personResults = ref<PersonSearchResult[]>([])
	const searchingPersons = shallowRef(false)
	const personSearchError = shallowRef('')
	const startingPersonId = shallowRef<number | null>(null)
	const conversationSearchResults = ref<ConversationSearchResult[]>([])
	const searchingConversations = shallowRef(false)
	const conversationSearchError = shallowRef('')
	const messageSearchResults = ref<ChatMessage[]>([])
	const searchingMessages = shallowRef(false)
	const messageSearchError = shallowRef('')
	const focusedMessageId = shallowRef<string | null>(null)
	const nextBatch = shallowRef<string | undefined>()
	let stopped = false
	let personSearchSequence = 0
	let conversationSearchSequence = 0
	let roomDetailsSequence = 0
	let messageSearchSequence = 0
	let messageSearchTimer: ReturnType<typeof window.setTimeout> | undefined
	let conversationSearchTimer: ReturnType<typeof window.setTimeout> | undefined
	let focusTimer: ReturnType<typeof window.setTimeout> | undefined

	const activeRoom = computed(() => rooms.value.find((room) => room.id === activeRoomId.value) ?? null)
	const messages = computed(() => activeRoom.value?.events ?? [])

	async function initialize() {
		loading.value = true
		error.value = ''
		try {
			status.value = await getStatus()
			if (!status.value.configured || !status.value.matrixConnected) {
				return
			}
			const response = await getRooms()
			rooms.value = response.rooms
			nextBatch.value = response.nextBatch ?? undefined
			if (rooms.value.length > 0) {
				await selectRoom(rooms.value[0].id)
			}
			void syncLoop()
		} catch (caught) {
			error.value = caught instanceof Error ? caught.message : 'Unable to load ChurchTools Chat.'
		} finally {
			loading.value = false
		}
	}

	async function selectRoom(roomId: string) {
		activeRoomId.value = roomId
		clearMessageSearch()
		roomDetails.value = null
		roomDetailsError.value = ''
		if (detailsOpen.value) {
			void loadRoomDetails(roomId)
		}
		const selectedRoom = rooms.value.find((room) => room.id === roomId)
		if (selectedRoom?.encrypted) {
			rooms.value = rooms.value.map((room) => room.id === roomId ? { ...room, events: [], unreadCount: 0 } : room)
			return
		}
		loadingMessages.value = true
		try {
			const response = await getMessages(roomId)
			rooms.value = rooms.value.map((room) => room.id === roomId ? { ...room, events: response.events, unreadCount: 0 } : room)
		} finally {
			loadingMessages.value = false
		}
	}

	async function send(body: string, replyTo?: ChatMessage) {
		const roomId = activeRoomId.value
		if (!roomId || body.trim() === '') return
		const txn = transactionId()
		const optimistic: ChatMessage = {
			id: txn,
			sender: status.value?.matrixUserId ?? '',
			senderName: status.value?.displayName || undefined,
			body: body.trim(),
			timestamp: Date.now(),
			status: 'sending',
			transactionId: txn,
			relatesTo: replyTo ? { 'm.in_reply_to': { event_id: replyTo.id } } : null,
		}
		rooms.value = rooms.value.map((room) => room.id === roomId
			? { ...room, events: [...room.events, optimistic], lastMessage: optimistic }
			: room)
		try {
			const sent = await sendMessage(roomId, optimistic.body, txn, replyTo?.id)
			rooms.value = rooms.value.map((room) => room.id === roomId
				? { ...room, events: room.events.map((message) => message.id === txn ? { ...message, id: sent.eventId, status: 'sent' } : message) }
				: room)
		} catch (caught) {
			rooms.value = rooms.value.map((room) => room.id === roomId
				? { ...room, events: room.events.map((message) => message.id === txn ? { ...message, status: 'failed' } : message) }
				: room)
			throw caught
		}
	}

	async function react(message: ChatMessage, emoji: string) {
		const roomId = activeRoomId.value
		if (!roomId || message.id.startsWith('nc-')) return
		await reactToMessage(roomId, message.id, emoji, transactionId())
		rooms.value = rooms.value.map((room) => room.id === roomId
			? {
				...room,
				events: room.events.map((event) => event.id === message.id
					? { ...event, reactions: { ...event.reactions, [emoji]: (event.reactions?.[emoji] ?? 0) + 1 } }
					: event),
			}
			: room)
	}

	async function retry(message: ChatMessage) {
		if (message.status !== 'failed') return
		const roomId = activeRoomId.value
		if (!roomId) return
		rooms.value = rooms.value.map((room) => room.id === roomId
			? { ...room, events: room.events.filter((item) => item.id !== message.id) }
			: room)
		await send(message.body)
	}

	async function searchPersons(query: string) {
		const normalizedQuery = query.trim()
		const sequence = ++personSearchSequence
		personSearchError.value = ''
		if (normalizedQuery.length < 2) {
			personResults.value = []
			searchingPersons.value = false
			return
		}

		searchingPersons.value = true
		try {
			const response = await searchPersonsRequest(normalizedQuery)
			if (sequence === personSearchSequence) {
				personResults.value = response.persons
			}
		} catch (caught) {
			if (sequence === personSearchSequence) {
				personResults.value = []
				personSearchError.value = getErrorMessage(caught)
			}
		} finally {
			if (sequence === personSearchSequence) {
				searchingPersons.value = false
			}
		}
	}

	function clearPersonSearch() {
		personSearchSequence += 1
		personResults.value = []
		personSearchError.value = ''
		searchingPersons.value = false
	}

	function clearConversationSearch() {
		conversationSearchSequence += 1
		if (conversationSearchTimer !== undefined) {
			window.clearTimeout(conversationSearchTimer)
			conversationSearchTimer = undefined
		}
		conversationSearchResults.value = []
		conversationSearchError.value = ''
		searchingConversations.value = false
	}

	function searchConversations(query: string) {
		const normalizedQuery = query.trim()
		const sequence = ++conversationSearchSequence
		if (conversationSearchTimer !== undefined) window.clearTimeout(conversationSearchTimer)
		conversationSearchError.value = ''
		if (normalizedQuery.length < 2) {
			conversationSearchResults.value = []
			searchingConversations.value = false
			return
		}

		conversationSearchTimer = window.setTimeout(async () => {
			searchingConversations.value = true
			try {
				const response = await searchConversationsRequest(normalizedQuery)
				if (sequence === conversationSearchSequence) {
					conversationSearchResults.value = response.results
				}
			} catch (caught) {
				if (sequence === conversationSearchSequence) {
					conversationSearchResults.value = []
					conversationSearchError.value = getErrorMessage(caught)
				}
			} finally {
				if (sequence === conversationSearchSequence) searchingConversations.value = false
			}
		}, 250)
	}

	function clearMessageSearch() {
		messageSearchSequence += 1
		if (messageSearchTimer !== undefined) {
			window.clearTimeout(messageSearchTimer)
			messageSearchTimer = undefined
		}
		messageSearchResults.value = []
		messageSearchError.value = ''
		searchingMessages.value = false
	}

	function searchMessages(query: string) {
		const roomId = activeRoomId.value
		const normalizedQuery = query.trim()
		const sequence = ++messageSearchSequence
		if (messageSearchTimer !== undefined) window.clearTimeout(messageSearchTimer)
		messageSearchError.value = ''
		if (!roomId || normalizedQuery.length < 2) {
			messageSearchResults.value = []
			searchingMessages.value = false
			return
		}

		messageSearchTimer = window.setTimeout(async () => {
			searchingMessages.value = true
			try {
				const response = await searchRoomMessagesRequest(roomId, normalizedQuery)
				if (sequence === messageSearchSequence && roomId === activeRoomId.value) {
					messageSearchResults.value = response.events
				}
			} catch (caught) {
				if (sequence === messageSearchSequence) {
					messageSearchResults.value = []
					messageSearchError.value = getErrorMessage(caught)
				}
			} finally {
				if (sequence === messageSearchSequence) searchingMessages.value = false
			}
		}, 250)
	}

	function focusMessage(message: ChatMessage) {
		const roomId = activeRoomId.value
		if (!roomId) return
		rooms.value = rooms.value.map((room) => room.id === roomId
			? {
				...room,
				events: [...room.events.filter((event) => event.id !== message.id), message]
					.sort((first, second) => first.timestamp - second.timestamp),
			}
			: room)
		focusedMessageId.value = message.id
		if (focusTimer !== undefined) window.clearTimeout(focusTimer)
		focusTimer = window.setTimeout(() => { focusedMessageId.value = null }, 2200)
	}

	async function startDirectChat(person: PersonSearchResult) {
		startingPersonId.value = person.id
		personSearchError.value = ''
		try {
			const directChat = await startDirectChatRequest(person.id)
			const response = await syncRooms()
			nextBatch.value = response.nextBatch ?? nextBatch.value
			rooms.value = mergeRooms(rooms.value, response.rooms)
			if (!rooms.value.some((room) => room.id === directChat.roomId)) {
				rooms.value = [{
					id: directChat.roomId,
					name: directChat.displayName,
					avatarUrl: person.imageUrl,
					encrypted: false,
					kind: 'direct',
					memberCount: 2,
					unreadCount: 0,
					lastMessage: null,
					events: [],
				}, ...rooms.value]
			}
			await selectRoom(directChat.roomId)
			clearPersonSearch()
			return directChat
		} catch (caught) {
			personSearchError.value = getErrorMessage(caught)
			throw caught
		} finally {
			startingPersonId.value = null
		}
	}

	async function loadRoomDetails(roomId = activeRoomId.value) {
		if (!roomId) return
		const sequence = ++roomDetailsSequence
		loadingRoomDetails.value = true
		roomDetailsError.value = ''
		try {
			const details = await getRoomDetails(roomId)
			if (sequence !== roomDetailsSequence || roomId !== activeRoomId.value) return
			roomDetails.value = details
			rooms.value = rooms.value.map((room) => room.id === roomId
				? {
					...room,
					name: details.name,
					avatarUrl: details.avatarUrl,
					kind: details.kind,
					memberCount: details.memberCount,
				}
				: room)
		} catch (caught) {
			if (sequence === roomDetailsSequence) {
				roomDetailsError.value = getErrorMessage(caught)
			}
		} finally {
			if (sequence === roomDetailsSequence) {
				loadingRoomDetails.value = false
			}
		}
	}

	function toggleDetails() {
		if (detailsOpen.value) {
			closeDetails()
			return
		}
		detailsOpen.value = true
		void loadRoomDetails()
	}

	function closeDetails() {
		detailsOpen.value = false
		clearMessageSearch()
		roomDetailsSequence += 1
		loadingRoomDetails.value = false
	}

	async function syncLoop() {
		while (!stopped && status.value?.matrixConnected) {
			try {
				const response = await syncRooms(nextBatch.value)
				nextBatch.value = response.nextBatch ?? nextBatch.value
				rooms.value = mergeRooms(rooms.value, response.rooms)
			} catch {
				await new Promise((resolve) => window.setTimeout(resolve, 5000))
			}
		}
	}

	onMounted(initialize)
	onBeforeUnmount(() => {
		stopped = true
		clearConversationSearch()
		clearMessageSearch()
		if (focusTimer !== undefined) window.clearTimeout(focusTimer)
	})

	return {
		status: readonly(status),
		rooms,
		activeRoomId: readonly(activeRoomId),
		activeRoom,
		messages,
		loading: readonly(loading),
		loadingMessages: readonly(loadingMessages),
		detailsOpen: readonly(detailsOpen),
		roomDetails: readonly(roomDetails),
		loadingRoomDetails: readonly(loadingRoomDetails),
		roomDetailsError: readonly(roomDetailsError),
		error: readonly(error),
		personResults: readonly(personResults),
		searchingPersons: readonly(searchingPersons),
		personSearchError: readonly(personSearchError),
		startingPersonId: readonly(startingPersonId),
		conversationSearchResults: readonly(conversationSearchResults),
		searchingConversations: readonly(searchingConversations),
		conversationSearchError: readonly(conversationSearchError),
		messageSearchResults: readonly(messageSearchResults),
		searchingMessages: readonly(searchingMessages),
		messageSearchError: readonly(messageSearchError),
		focusedMessageId: readonly(focusedMessageId),
		selectRoom,
		searchPersons,
		clearPersonSearch,
		searchConversations,
		clearConversationSearch,
		searchMessages,
		clearMessageSearch,
		focusMessage,
		startDirectChat,
		send,
		retry,
		react,
		toggleDetails,
		closeDetails,
		loadRoomDetails,
	}
}
