import type { ChatMessage } from '../types/chat'

interface ReplyRelation {
	event_id?: unknown
	rel_type?: unknown
	'm.in_reply_to'?: { event_id?: unknown }
	[key: string]: unknown
}

function eventId(value: unknown): string | null {
	if (typeof value !== 'object' || value === null) return null
	const eventIdValue = (value as { event_id?: unknown }).event_id
	return typeof eventIdValue === 'string' && eventIdValue !== '' ? eventIdValue : null
}

export function getReplyTargetId(message: ChatMessage): string | null {
	const relation = message.relatesTo
	if (typeof relation !== 'object' || relation === null) return null
	const relations = relation as ReplyRelation
	const inReplyTo = eventId(relations['m.in_reply_to'])
	if (inReplyTo !== null) return inReplyTo
	// Thread relations point at a root event; use the specific reply target when present.
	if (relations.rel_type === 'm.thread') {
		const threadTarget = eventId(relations['m.in_reply_to'])
		if (threadTarget !== null) return threadTarget
		return typeof relations.event_id === 'string' && relations.event_id !== '' ? relations.event_id : null
	}
	return null
}

/**
 * Extract the quoted text from a Matrix rich-reply fallback in the message body
 * (leading "> " lines) when no explicit relation made it to the client.
 */
export function getReplyFallbackQuote(message: ChatMessage): string | null {
	if (getReplyTargetId(message) !== null) return null
	const quoted: string[] = []
	for (const line of message.body.split(/\r?\n/)) {
		if (line.startsWith('>')) {
			quoted.push(line.replace(/^>\s?/, ''))
		} else {
			break
		}
	}
	const quote = quoted.join('\n').trim()
	if (quote === '') return null
	// The rich-reply fallback prefixes the quoted text with the sender: "> <@user:server> text".
	return quote.replace(/^<@[^>]+>\s?/, '')
}

/** Whether a message participates in a reply, even if the original is not resolvable. */
export function isReplyMessage(message: ChatMessage): boolean {
	return getReplyTargetId(message) !== null || getReplyFallbackQuote(message) !== null
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
