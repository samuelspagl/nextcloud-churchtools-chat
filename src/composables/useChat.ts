import { computed, onBeforeUnmount, onMounted, readonly, ref, shallowRef, watch } from 'vue'
import { showError } from '@nextcloud/dialogs'
import {
	getErrorCode,
	getErrorMessage,
	getErrorValue,
	getEvent,
	getMessages,
	getRoomDetails,
	getRooms,
	getStatus,
	reactToMessage,
	deleteMessage as deleteMessageRequest,
	editMessage as editMessageRequest,
	searchConversations as searchConversationsRequest,
	searchPersons as searchPersonsRequest,
	searchRoomMessages as searchRoomMessagesRequest,
	sendMessage,
	setFullyRead,
	setTyping as setTypingRequest,
	startDirectChat as startDirectChatRequest,
	syncRooms,
} from '../services/chatApi'
import type { ChatMessage, ChatRoom, ChatStatus, ConversationSearchResult, PersonSearchResult, RoomDetails } from '../types/chat'
import { mergeRooms } from '../utils/rooms'
import { backoffDelay } from '../utils/backoff'
import { buildReplyRelation, getReplyTargetId } from '../utils/relations'

function transactionId(): string {
	return `nc-${crypto.randomUUID()}`
}

function removeReaction(reactions: Record<string, number> | undefined, emoji: string): Record<string, number> {
	const next = { ...(reactions ?? {}) }
	if ((next[emoji] ?? 0) <= 1) {
		delete next[emoji]
	} else {
		next[emoji] -= 1
	}
	return next
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
	const sessionExpired = shallowRef(false)
	const replyTargets = shallowRef<Record<string, ChatMessage>>({})
	const pendingReplyTargets = new Set<string>()
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
			try {
				// Like Element's continuous sync, reconcile with a fresh sync before painting
				// so unread counts reflect the latest notification_count.
				const fresh = await syncRooms(nextBatch.value)
				nextBatch.value = fresh.nextBatch ?? nextBatch.value
				rooms.value = mergeRooms(rooms.value, fresh.rooms)
			} catch {
				// Keep the getRooms snapshot if the reconcile sync fails.
			}
			void syncLoop()
		} catch (caught) {
			error.value = caught instanceof Error ? caught.message : 'Unable to load ChurchTools Chat.'
		} finally {
			loading.value = false
		}
	}

	async function selectRoom(roomId: string) {
		const previousRoomId = activeRoomId.value
		activeRoomId.value = roomId
		clearMessageSearch()
		roomDetails.value = null
		roomDetailsError.value = ''
		if (detailsOpen.value) {
			void loadRoomDetails(roomId)
		}
		const selectedRoom = rooms.value.find((room) => room.id === roomId)
		if (selectedRoom?.encrypted) {
			rooms.value = rooms.value.map((room) => room.id === roomId ? { ...room, events: [] } : room)
			return
		}
		loadingMessages.value = true
		try {
			const response = await getMessages(roomId)
			rooms.value = rooms.value.map((room) => room.id === roomId
				? {
					...room,
					events: response.events,
					prevBatch: response.start ?? null,
					hasMore: response.start !== null,
					lastMessage: response.events[response.events.length - 1] ?? room.lastMessage,
				}
				: room)
			const opened = rooms.value.find((room) => room.id === roomId)
			const latest = opened?.events[opened.events.length - 1]
			if (latest && !latest.id.startsWith('nc-')) {
				await setFullyRead(roomId, latest.id).catch((error) => console.warn('Failed to mark room read', error))
			}
		} finally {
			loadingMessages.value = false
		}
		if (previousRoomId && previousRoomId !== roomId) {
			const previous = rooms.value.find((room) => room.id === previousRoomId)
			const previousLatest = previous?.events[previous.events.length - 1]
			if (previousLatest && !previousLatest.id.startsWith('nc-')) {
				void setFullyRead(previousRoomId, previousLatest.id).catch((error) => console.warn('Failed to mark room read', error))
			}
		}
	}

	async function loadOlderMessages(roomId: string) {
		const room = rooms.value.find((existing) => existing.id === roomId)
		if (!room || !room.prevBatch) return
		try {
			const response = await getMessages(roomId, room.prevBatch)
			const older = response.events
			rooms.value = rooms.value.map((existing) => existing.id === roomId
				? { ...existing, events: [...older, ...existing.events], prevBatch: response.start ?? null, hasMore: older.length > 0 && response.start !== null }
				: existing)
		} catch {
			// Keep the existing prevBatch so the user can retry loading older messages.
		}
	}

	async function send(body: string, options?: { replyTo?: ChatMessage; transactionId?: string; mentions?: string[] }) {
		const roomId = activeRoomId.value
		const replyTo = options?.replyTo
		if (!roomId || body.trim() === '') return
		const txn = options?.transactionId ?? transactionId()
		const optimistic: ChatMessage = {
			id: txn,
			sender: status.value?.matrixUserId ?? '',
			senderName: status.value?.displayName || undefined,
			body: body.trim(),
			timestamp: Date.now(),
			status: 'sending',
			transactionId: txn,
			relatesTo: replyTo ? buildReplyRelation(replyTo.id) : null,
		}
		rooms.value = rooms.value.map((room) => room.id === roomId
			? { ...room, events: [...room.events, optimistic], lastMessage: optimistic }
			: room)
		try {
			const sent = await sendMessage(roomId, optimistic.body, txn, replyTo?.id, options?.mentions)
			rooms.value = rooms.value.map((room) => room.id === roomId
				? { ...room, events: room.events.map((message) => message.id === txn ? { ...message, id: sent.eventId, status: 'sent' } : message) }
				: room)
			if (!sent.eventId.startsWith('nc-')) {
				void setFullyRead(roomId, sent.eventId).catch((error) => console.warn('Failed to mark room read', error))
			}
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
		const result = await reactToMessage(roomId, message.id, emoji, transactionId())
		rooms.value = rooms.value.map((room) => room.id === roomId
			? {
				...room,
				events: room.events.map((event) => event.id === message.id
					? {
						...event,
						reactions: { ...event.reactions, [emoji]: (event.reactions?.[emoji] ?? 0) + 1 },
						ownReactions: [...(event.ownReactions ?? []), { key: emoji, eventId: result.eventId }],
					}
					: event),
			}
			: room)
	}

	async function unreact(message: ChatMessage, emoji: string) {
		const roomId = activeRoomId.value
		if (!roomId || message.id.startsWith('nc-')) return
		const own = message.ownReactions?.find((reaction) => reaction.key === emoji)
		if (!own) return
		await deleteMessageRequest(roomId, own.eventId)
		rooms.value = rooms.value.map((room) => room.id === roomId
			? {
				...room,
				events: room.events.map((event) => event.id === message.id
					? {
						...event,
						reactions: removeReaction(event.reactions, emoji),
						ownReactions: (event.ownReactions ?? []).filter((reaction) => reaction.key !== emoji),
					}
					: event),
			}
			: room)
	}

	async function deleteMessage(message: ChatMessage) {
		const roomId = activeRoomId.value
		if (!roomId || message.id.startsWith('nc-')) return
		try {
			await deleteMessageRequest(roomId, message.id)
			rooms.value = rooms.value.map((room) => room.id === roomId
				? {
					...room,
					events: room.events.map((event) => event.id === message.id
						? { ...event, redacted: true, body: '', attachment: undefined, reactions: undefined }
						: event),
				}
				: room)
		} catch (error) {
			showError(getErrorMessage(error))
		}
	}

	async function editMessage(message: ChatMessage, body: string) {
		const roomId = activeRoomId.value
		const trimmed = body.trim()
		if (!roomId || message.id.startsWith('nc-') || trimmed === '') return
		try {
			await editMessageRequest(roomId, message.id, trimmed, transactionId())
			rooms.value = rooms.value.map((room) => room.id === roomId
				? {
					...room,
					events: room.events.map((event) => event.id === message.id
						? { ...event, body: trimmed, edited: true }
						: event),
				}
				: room)
		} catch (error) {
			showError(getErrorMessage(error))
		}
	}

	async function retry(message: ChatMessage) {
		if (message.status !== 'failed') return
		const roomId = activeRoomId.value
		if (!roomId) return
		rooms.value = rooms.value.map((room) => room.id === roomId
			? { ...room, events: room.events.filter((item) => item.id !== message.id) }
			: room)
		await send(message.body, { transactionId: message.transactionId })
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

	async function fetchReplyTarget(roomId: string, eventId: string) {
		if (pendingReplyTargets.has(eventId) || replyTargets.value[eventId]) return
		pendingReplyTargets.add(eventId)
		try {
			const target = await getEvent(roomId, eventId)
			replyTargets.value = { ...replyTargets.value, [eventId]: target }
		} catch {
			// Leave the reply target unresolved; the timeline renders a placeholder.
		} finally {
			pendingReplyTargets.delete(eventId)
		}
	}

	watch(activeRoomId, () => {
		replyTargets.value = {}
		pendingReplyTargets.clear()
	})

	watch(messages, (list) => {
		const roomId = activeRoomId.value
		if (!roomId) return
		const loadedIds = new Set(list.map((message) => message.id))
		for (const message of list) {
			const targetId = getReplyTargetId(message)
			if (targetId !== null && !loadedIds.has(targetId) && !replyTargets.value[targetId]) {
				void fetchReplyTarget(roomId, targetId)
			}
		}
	}, { immediate: true })

	async function setTyping(typing: boolean) {
		const roomId = activeRoomId.value
		if (!roomId) return
		try {
			await setTypingRequest(roomId, typing)
		} catch {
			// Typing state is ephemeral and best-effort; ignore failures.
		}
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
		let attempt = 0
		while (!stopped && status.value?.matrixConnected && !sessionExpired.value) {
			try {
				const response = await syncRooms(nextBatch.value)
				nextBatch.value = response.nextBatch ?? nextBatch.value
				rooms.value = mergeRooms(rooms.value, response.rooms)
				attempt = 0
				await reloadLimitedRooms(response.rooms)
			} catch (caught) {
				if (getErrorCode(caught) === 'matrix_session_expired') {
					sessionExpired.value = true
					if (status.value) {
						status.value = { ...status.value, matrixConnected: false }
					}
					return
				}
				const retryAfter = getErrorValue(caught)
				const delay = backoffDelay(attempt, retryAfter)
				attempt += 1
				await new Promise((resolve) => window.setTimeout(resolve, delay))
			}
		}
	}

	async function reloadLimitedRooms(incoming: ChatRoom[]) {
		for (const room of incoming) {
			if (!room.limited) continue
			const roomId = room.id
			try {
				const response = await getMessages(roomId, room.prevBatch ?? undefined)
				rooms.value = mergeRooms(rooms.value, [{ ...room, events: response.events, limited: false }])
			} catch (caught) {
				if (getErrorCode(caught) === 'matrix_session_expired') {
					throw caught
				}
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
		sessionExpired: readonly(sessionExpired),
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
		replyTargets: readonly(replyTargets),
		selectRoom,
		loadOlderMessages,
		searchPersons,
		clearPersonSearch,
		searchConversations,
		clearConversationSearch,
		searchMessages,
		clearMessageSearch,
		focusMessage,
		startDirectChat,
		send,
		setTyping,
		retry,
		react,
		unreact,
		deleteMessage,
		editMessage,
		toggleDetails,
		closeDetails,
		loadRoomDetails,
	}
}
