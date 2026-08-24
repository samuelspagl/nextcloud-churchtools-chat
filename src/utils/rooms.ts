import type { ChatMessage, ChatRoom } from '../types/chat'

function latestMessage(...candidates: (ChatMessage | null | undefined)[]): ChatMessage | null {
	let best: ChatMessage | null = null
	for (const candidate of candidates) {
		if (!candidate) continue
		if (!best || (candidate.timestamp ?? 0) > (best.timestamp ?? 0)) {
			best = candidate
		} else if ((candidate.timestamp ?? 0) === (best.timestamp ?? 0)) {
			// Same timestamp: prefer the candidate with a non-empty body (e.g. attachment previews).
			if (!best.body && candidate.body) {
				best = candidate
			}
		}
	}
	return best
}

export function mergeRooms(current: ChatRoom[], incoming: ChatRoom[]): ChatRoom[] {
	const rooms = new Map(current.map((room) => [room.id, room]))
	for (const update of incoming) {
		const existing = rooms.get(update.id)
		const eventMap = new Map((existing?.events ?? []).map((event) => [event.id, event]))
		for (const event of update.events) {
			const known = eventMap.get(event.id)
			eventMap.set(event.id, {
				...known,
				...event,
				senderName: event.senderName || known?.senderName,
				senderAvatarUrl: event.senderAvatarUrl ?? known?.senderAvatarUrl ?? null,
			})
		}
		const incomingNameIsFallback = update.name === 'Conversation' || /^@[^:]+:/.test(update.name)
		// An empty-timeline sync delta carries no message information. Never let it clear
		// the events/lastMessage we already know about for an existing room.
		if (existing && update.events.length === 0 && update.lastMessage == null) {
			rooms.set(update.id, {
				...existing,
				...update,
				name: existing && incomingNameIsFallback ? existing.name : update.name,
				avatarUrl: update.avatarUrl ?? existing?.avatarUrl ?? null,
				events: existing.events,
				lastMessage: existing.lastMessage,
			})
			continue
		}
		const events = [...eventMap.values()].sort((a, b) => a.timestamp - b.timestamp)
		const derivedLast = events.length > 0 ? events[events.length - 1] : null
		rooms.set(update.id, {
			...existing,
			...update,
			name: existing && incomingNameIsFallback ? existing.name : update.name,
			avatarUrl: update.avatarUrl ?? existing?.avatarUrl ?? null,
			events,
			lastMessage: latestMessage(update.lastMessage, existing?.lastMessage, derivedLast),
		})
	}
	return [...rooms.values()].sort((a, b) =>
		(b.lastMessage?.timestamp ?? 0) - (a.lastMessage?.timestamp ?? 0)
		|| (a.id < b.id ? -1 : a.id > b.id ? 1 : 0))
}
