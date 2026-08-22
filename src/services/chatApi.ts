import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type {
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

export async function sendMessage(roomId: string, body: string, transactionId: string, replyTo?: string): Promise<{ eventId: string; transactionId: string }> {
	const response = await axios.post<ApiEnvelope<{ eventId: string; transactionId: string }>>(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages`),
		{ body, transactionId, replyTo },
	)
	return response.data.data
}

export async function reactToMessage(roomId: string, eventId: string, emoji: string, transactionId: string): Promise<void> {
	await axios.post(
		endpoint(`/api/rooms/${encodeURIComponent(roomId)}/messages/${encodeURIComponent(eventId)}/reactions`),
		{ emoji, transactionId },
	)
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

export async function saveSettings(tenantUrl: string, token: string, matrixPassword: string): Promise<SettingsState> {
	const response = await axios.post<ApiEnvelope<SettingsState>>(endpoint('/api/settings'), { tenantUrl, token, matrixPassword })
	return response.data.data
}

export async function deleteSettings(): Promise<void> {
	await axios.delete(endpoint('/api/settings'))
}

export function getErrorMessage(error: unknown): string {
	if (typeof error === 'object' && error !== null && 'response' in error) {
		const response = (error as { response?: { data?: { error?: { message?: string } } } }).response
		const message = response?.data?.error?.message
		if (message) {
			return message
		}
	}
	return 'The request could not be completed.'
}
