import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type {
	ChatMessage,
	ChatStatus,
	ConversationSearchResponse,
	DirectChatResponse,
	MessageSearchResponse,
	MessagesResponse,
	PersonSearchResponse,
	RoomDetails,
	RoomsResponse,
	SettingsState,
	SyncResponse,
	AdminSettingsState,
} from '../types/chat'

interface ApiEnvelope<T> {
	data: T
}

function endpoint(path: string): string {
	return generateUrl(`/apps/churchtools_chat${path}`)
}

export async function getStatus(): Promise<ChatStatus> {
	const response = await axios.get<ApiEnvelope<ChatStatus>>(endpoint('/api/status'))
	return response.data.data
}

export async function getRooms(): Promise<RoomsResponse> {
	const response = await axios.get<ApiEnvelope<RoomsResponse>>(endpoint('/api/rooms'))
	return response.data.data
}

export async function searchPersons(query: string): Promise<PersonSearchResponse> {
	const response = await axios.get<ApiEnvelope<PersonSearchResponse>>(endpoint('/api/persons'), { params: { query } })
	return response.data.data
}

export async function searchConversations(query: string): Promise<ConversationSearchResponse> {
	const response = await axios.get<ApiEnvelope<ConversationSearchResponse>>(endpoint('/api/search'), { params: { query } })
	return response.data.data
}

export async function startDirectChat(personId: number): Promise<DirectChatResponse> {
	const response = await axios.post<ApiEnvelope<DirectChatResponse>>(endpoint('/api/direct-chats'), { personId })
	return response.data.data
}

export async function getMessages(roomId: string, from?: string): Promise<MessagesResponse> {
	const response = await axios.get<ApiEnvelope<MessagesResponse>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages`),
		{ params: { from } },
	)
	return response.data.data
}

export async function getEvent(roomId: string, eventId: string): Promise<ChatMessage> {
	const response = await axios.get<ApiEnvelope<ChatMessage>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages/${encodeURIComponent(eventId)}`),
	)
	return response.data.data
}

export async function searchRoomMessages(roomId: string, query: string): Promise<MessageSearchResponse> {
	const response = await axios.get<ApiEnvelope<MessageSearchResponse>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/search`),
		{ params: { query } },
	)
	return response.data.data
}

export async function getRoomDetails(roomId: string): Promise<RoomDetails> {
	const response = await axios.get<ApiEnvelope<RoomDetails>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/details`),
	)
	return response.data.data
}

export async function sendMessage(roomId: string, body: string, transactionId: string, replyTo?: string, mentions?: string[]): Promise<{ eventId: string; transactionId: string }> {
	const response = await axios.post<ApiEnvelope<{ eventId: string; transactionId: string }>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages`),
		{ body, transactionId, replyTo, mentions },
	)
	return response.data.data
}

export async function reactToMessage(roomId: string, eventId: string, emoji: string, transactionId: string): Promise<{ eventId: string; transactionId: string }> {
	const response = await axios.post<ApiEnvelope<{ eventId: string; transactionId: string }>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages/${encodeURIComponent(eventId)}/reactions`),
		{ emoji, transactionId },
	)
	return response.data.data
}

export async function editMessage(roomId: string, eventId: string, body: string, transactionId: string): Promise<{ eventId: string; transactionId: string }> {
	const response = await axios.put<ApiEnvelope<{ eventId: string; transactionId: string }>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages/${encodeURIComponent(eventId)}`),
		{ body, transactionId },
	)
	return response.data.data
}

export async function setFullyRead(roomId: string, eventId: string): Promise<void> {
	await axios.post(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/read-marker`),
		{ eventId },
	)
}

export async function setTyping(roomId: string, typing: boolean): Promise<void> {
	await axios.post(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/typing`),
		{ typing },
	)
}

export async function deleteMessage(roomId: string, eventId: string): Promise<void> {
	await axios.post(endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages/${encodeURIComponent(eventId)}/redact`))
}

export async function saveAttachment(mxc: string, directory: string, filename: string): Promise<{ path: string }> {
	const response = await axios.post<ApiEnvelope<{ path: string }>>(endpoint('/api/media/save'), { mxc, directory, filename })
	return response.data.data
}

export async function syncRooms(since?: string): Promise<SyncResponse> {
	const response = await axios.get<ApiEnvelope<SyncResponse>>(endpoint('/api/sync'), { params: { since } })
	return response.data.data
}

export async function getSettings(): Promise<SettingsState> {
	const response = await axios.get<ApiEnvelope<SettingsState>>(endpoint('/api/settings'))
	return response.data.data
}

export async function saveSettings(token: string, matrixPassword: string): Promise<SettingsState> {
	const response = await axios.post<ApiEnvelope<SettingsState>>(endpoint('/api/settings'), { token, matrixPassword })
	return response.data.data
}

export async function deleteSettings(): Promise<void> {
	await axios.delete(endpoint('/api/settings'))
}

export async function getAdminSettings(): Promise<AdminSettingsState> {
	const response = await axios.get<ApiEnvelope<AdminSettingsState>>(endpoint('/api/admin/settings'))
	return response.data.data
}

export async function saveAdminSettings(churchToolsTenantUrl: string, matrixServerUrl: string): Promise<AdminSettingsState> {
	const response = await axios.post<ApiEnvelope<AdminSettingsState>>(endpoint('/api/admin/settings'), { churchToolsTenantUrl, matrixServerUrl })
	return response.data.data
}

export function getErrorMessage(error: unknown): string {
	const message = readErrorField(error, 'message')
	if (typeof message === 'string' && message !== '') {
		return message
	}
	return 'The request could not be completed.'
}

function readErrorField(error: unknown, field: 'code' | 'message' | 'value'): string | number | undefined {
	if (typeof error === 'object' && error !== null && 'response' in error) {
		const response = (error as { response?: { data?: { error?: Record<string, unknown> } } }).response
		const value = response?.data?.error?.[field]
		if (typeof value === 'string' || typeof value === 'number') {
			return value
		}
	}
	return undefined
}

export function getErrorCode(error: unknown): string | null {
	const code = readErrorField(error, 'code')
	return typeof code === 'string' ? code : null
}

export function getErrorValue(error: unknown): number | null {
	const value = readErrorField(error, 'value')
	return typeof value === 'number' ? value : null
}
