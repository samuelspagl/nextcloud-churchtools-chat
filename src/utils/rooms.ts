import type { ChatRoom } from '../types/chat'

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
		rooms.set(update.id, {
			...existing,
			...update,
			name: existing && incomingNameIsFallback ? existing.name : update.name,
			avatarUrl: update.avatarUrl ?? existing?.avatarUrl ?? null,
			events: [...eventMap.values()].sort((a, b) => a.timestamp - b.timestamp),
		})
	}
	return [...rooms.values()].sort((a, b) => (b.lastMessage?.timestamp ?? 0) - (a.lastMessage?.timestamp ?? 0))
}
