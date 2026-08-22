import type { ChatMessage } from '../types/chat'

export function messageSenderLabel(message: ChatMessage, currentUserId: string, ownLabel: string): string {
	if (message.sender === currentUserId) return ownLabel
	return message.senderName?.trim() || message.sender
}
