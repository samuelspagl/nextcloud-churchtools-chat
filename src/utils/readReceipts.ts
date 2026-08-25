import type { ChatMessage } from '../types/chat'

/** The latest m.read event id from any user other than the current user. */
export function otherReadEventId(readReceipts: Record<string, string> | undefined, currentUserId: string): string | null {
	for (const [userId, eventId] of Object.entries(readReceipts ?? {})) {
		if (userId !== currentUserId) return eventId
	}
	return null
}

/** The index of the read receipt event in the loaded message list, or null when not loaded. */
export function readReceiptIndex(events: readonly ChatMessage[], readEventId: string | null): number | null {
	if (readEventId === null) return null
	const index = events.findIndex((event) => event.id === readEventId)
	return index === -1 ? null : index
}

/**
 * Id of the last own message that another participant has read (DM read receipts),
 * or null when there is no read position to display.
 */
export function lastReadOwnMessageId(
	events: readonly ChatMessage[],
	readReceipts: Record<string, string> | undefined,
	currentUserId: string,
): string | null {
	const readIndex = readReceiptIndex(events, otherReadEventId(readReceipts, currentUserId))
	if (readIndex === null) return null
	let lastRead: string | null = null
	for (let index = 0; index <= readIndex; index += 1) {
		const event = events[index]
		if (event && event.sender === currentUserId) {
			lastRead = event.id
		}
	}
	return lastRead
}