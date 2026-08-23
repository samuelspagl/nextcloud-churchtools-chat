export interface IntegrationError {
	code: string
	message: string
}

export interface ChatStatus {
	configured: boolean
	tenantUrl: string
	personId: number | null
	personGuid: string
	displayName: string
	canChat: boolean | null
	matrixConnected: boolean
	matrixUserId: string
	capabilities: {
		rooms: boolean
		messages: boolean
		send: boolean
		directChat: boolean
		markdown: boolean
		smartPicker: boolean
	}
}

export interface ChatMessage {
	id: string
	sender: string
	senderName?: string
	senderAvatarUrl?: string | null
	body: string
	timestamp: number
	edited?: boolean
	relatesTo?: Record<string, unknown> | null
	reactions?: Record<string, number>
	status?: 'sending' | 'sent' | 'failed'
	transactionId?: string
	attachment?: ChatAttachment
}

export interface ChatAttachment {
	kind: 'image' | 'file' | 'audio' | 'video'
	mxcUrl: string
	filename: string
	mimeType: string | null
	size: number | null
}

export interface ChatRoom {
	id: string
	name: string
	avatarUrl: string | null
	encrypted: boolean
	kind: 'direct' | 'group'
	memberCount: number
	unreadCount: number
	lastMessage: ChatMessage | null
	events: ChatMessage[]
}

export interface RoomMember {
	id: string
	displayName: string
	avatarUrl: string | null
	membership: 'join' | 'invite'
}

export interface RoomDetails {
	roomId: string
	name: string
	avatarUrl: string | null
	kind: 'direct' | 'group'
	memberCount: number
	topic: string
	canonicalAlias: string | null
	encrypted: boolean
	creator: string | null
	joinRule: string | null
	historyVisibility: string | null
	members: readonly RoomMember[]
}

export interface RoomsResponse {
	rooms: ChatRoom[]
	nextBatch: string | null
	churchToolsChats: Array<Record<string, unknown>>
}

export interface MessagesResponse {
	events: ChatMessage[]
	start: string | null
	end: string | null
}

export interface MessageSearchResponse {
	events: ChatMessage[]
}

export interface ConversationSearchResult {
	roomId: string
	message: ChatMessage
}

export interface ConversationSearchResponse {
	results: ConversationSearchResult[]
}

export interface SyncResponse {
	rooms: ChatRoom[]
	nextBatch: string | null
}

export interface PersonSearchResult {
	id: number
	guid: string
	matrixUserId: string
	displayName: string
	imageUrl: string | null
	info: string
}

export interface PersonSearchResponse {
	persons: PersonSearchResult[]
}

export interface DirectChatResponse {
	roomId: string
	created: boolean
	matrixUserId: string
	displayName: string
	canChat: boolean
}

export interface SettingsState {
	configured: boolean
	tenantUrl: string
	personId: number | null
	personGuid: string
	displayName: string
	canChat: boolean | null
	matrixConnected: boolean
	matrixUserId: string
	bootstrapError?: IntegrationError | null
}

export interface AdminSettingsState {
	churchToolsTenantUrl: string
	matrixServerUrl: string
}
