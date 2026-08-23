import type { ChatMessage } from '../types/chat'

export function getReplyTargetId(message: ChatMessage): string | null {
	const relation = message.relatesTo
	if (relation && typeof relation === 'object' && 'm.in_reply_to' in relation) {
		const target = (relation as Record<string, { event_id?: string }>)['m.in_reply_to']
		if (target && typeof target === 'object' && typeof target.event_id === 'string') {
			return target.event_id
		}
	}
	return null
}

export function buildReplyRelation(eventId: string): Record<string, unknown> {
	return { 'm.in_reply_to': { event_id: eventId } }
}

export function buildAnnotationRelation(eventId: string, key: string): Record<string, unknown> {
	return { rel_type: 'm.annotation', event_id: eventId, key }
}

export function buildReplaceRelation(eventId: string): Record<string, unknown> {
	return { rel_type: 'm.replace', event_id: eventId }
}

export function getReactionKeys(message: ChatMessage): string[] {
	return message.reactions ? Object.keys(message.reactions) : []
}
