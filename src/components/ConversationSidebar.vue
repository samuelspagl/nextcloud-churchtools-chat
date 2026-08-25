<script setup lang="ts">
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import type { ChatMessage, ChatRoom, ConversationSearchResult } from '../types/chat'
import { displayableAvatarUrl } from '../utils/avatar'
import { typingLabel } from '../utils/typing'

interface VisibleConversationSearchResult {
	room: ChatRoom
	message?: ChatMessage
}

const props = defineProps<{
	rooms: readonly ChatRoom[]
	activeRoomId: string | null
	loading: boolean
	canStartChat: boolean
	query: string
	searchResults: readonly ConversationSearchResult[]
	searching: boolean
	searchError: string
}>()

const emit = defineEmits<{
	select: [roomId: string]
	newChat: []
	'update:query': [query: string]
	selectSearchResult: [result: { roomId: string; message?: ChatMessage }]
}>()

const hasSearchTerm = computed(() => props.query.trim().length >= 2)
const visibleRooms = computed<VisibleConversationSearchResult[]>(() => {
	const query = props.query.trim().toLocaleLowerCase()
	if (query === '') return props.rooms.map((room) => ({ room }))
	const results = new Map<string, VisibleConversationSearchResult>()
	for (const room of props.rooms) {
		if (room.name.toLocaleLowerCase().includes(query)) results.set(room.id, { room })
	}
	for (const result of props.searchResults) {
		const room = props.rooms.find((candidate) => candidate.id === result.roomId)
		if (!room) continue
		const existing = results.get(room.id)
		results.set(room.id, { room, message: existing?.message ?? result.message })
	}
	return [...results.values()]
})

function formatTime(timestamp?: number): string {
	if (!timestamp) return ''
	return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(timestamp)
}

function searchPreview(result: VisibleConversationSearchResult): string {
	if (result.room.typingUsers?.length) return typingLabel(result.room.typingUsers)
	if (result.message) return `${result.message.senderName || result.message.sender}: ${result.message.body || result.message.attachment?.filename || ''}`
	if (result.room.encrypted) return t('churchtools_chat', 'Encrypted room — unsupported')
	const last = result.room.lastMessage
	if (!last) return t('churchtools_chat', 'No messages yet')
	return last.body || last.attachment?.filename || t('churchtools_chat', 'No messages yet')
}

const newChatIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-8l-5 4v-4H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 2v11h5v1.8l2.2-1.8H20V5H4Zm9 2v3h3v2h-3v3h-2v-3H8v-2h3V7h2Z"/></svg>'
</script>

<template>
	<aside class="conversation-sidebar" aria-label="Conversations">
		<div class="conversation-sidebar__heading">
			<div class="conversation-sidebar__title">
				<h1>{{ t('churchtools_chat', 'ChurchTools Chat') }}</h1>
				<NcButton
					variant="tertiary"
					:disabled="!canStartChat"
					:aria-label="t('churchtools_chat', 'New chat')"
					:title="t('churchtools_chat', 'New chat')"
					@click="emit('newChat')">
					<template #icon><NcIconSvgWrapper :svg="newChatIcon" /></template>
				</NcButton>
			</div>
			<NcInputField
				:model-value="query"
				:label="t('churchtools_chat', 'Search conversations')"
				@update:model-value="emit('update:query', String($event))" />
		</div>

		<div v-if="loading" class="conversation-sidebar__state">
			<NcLoadingIcon :size="32" />
			<span>{{ t('churchtools_chat', 'Loading conversations…') }}</span>
		</div>
		<div v-else-if="hasSearchTerm && searching && visibleRooms.length === 0" class="conversation-sidebar__state conversation-sidebar__state--search">
			<NcLoadingIcon :size="24" />
			<span>{{ t('churchtools_chat', 'Searching conversations…') }}</span>
		</div>
		<p v-else-if="visibleRooms.length === 0" class="conversation-sidebar__state">
			{{ t('churchtools_chat', 'No conversations found.') }}
		</p>
		<ul v-else class="conversation-list">
			<li v-for="result in visibleRooms" :key="result.room.id">
				<button
					class="conversation"
					:class="{ 'conversation--active': result.room.id === activeRoomId }"
					:type="'button'"
					:aria-current="result.room.id === activeRoomId ? 'page' : undefined"
					@click="hasSearchTerm ? emit('selectSearchResult', { roomId: result.room.id, message: result.message }) : emit('select', result.room.id)">
					<NcAvatar
						:url="displayableAvatarUrl(result.room.avatarUrl)"
						:display-name="result.room.name"
						:is-no-user="true"
						:disable-menu="true"
						:size="40" />
					<span class="conversation__body">
						<span class="conversation__topline">
							<strong>{{ result.room.name }}</strong>
							<time v-if="result.message || result.room.lastMessage">{{ formatTime((result.message || result.room.lastMessage)?.timestamp) }}</time>
						</span>
						<span class="conversation__preview" :class="{ 'conversation__preview--typing': result.room.typingUsers?.length }">{{ searchPreview(result) }}</span>
					</span>
					<span v-if="result.room.unreadCount > 0" class="conversation__badge" :aria-label="`${result.room.unreadCount} unread messages`">
						{{ result.room.unreadCount > 99 ? '99+' : result.room.unreadCount }}
					</span>
				</button>
			</li>
		</ul>
		<p v-if="!loading && hasSearchTerm && searchError" class="conversation-sidebar__error" role="status">{{ searchError }}</p>
	</aside>
</template>

<style scoped>
.conversation-sidebar { display: flex; height: 100%; flex-direction: column; background: var(--color-main-background); }
.conversation-sidebar__heading { display: grid; gap: 12px; padding: 16px; border-bottom: 1px solid var(--color-border); }
.conversation-sidebar__heading h1 { margin: 0; font-size: 20px; }
.conversation-sidebar__title { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.conversation-sidebar__state { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 120px; color: var(--color-text-maxcontrast); }
.conversation-sidebar__state--search { min-height: 48px; padding: 8px 16px; }
.conversation-sidebar__error { margin: 0; padding: 8px 16px; border-top: 1px solid var(--color-border); color: var(--color-error-text); font-size: 13px; }
.conversation-list { min-height: 0; flex: 1; overflow-y: auto; margin: 0; padding: 8px; list-style: none; }
.conversation { display: flex; width: 100%; min-height: 64px; align-items: center; gap: 10px; padding: 8px; border: 0; border-radius: var(--border-radius-large); color: var(--color-main-text); background: transparent; text-align: start; cursor: pointer; }
.conversation:hover, .conversation:focus-visible { background: var(--color-background-hover); }
.conversation:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: -2px; }
.conversation--active { background: var(--color-primary-element-light); }
.conversation__body { min-width: 0; flex: 1; }
.conversation__topline { display: flex; justify-content: space-between; gap: 8px; }
.conversation__topline strong, .conversation__preview { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.conversation__topline time { color: var(--color-text-maxcontrast); font-size: 12px; }
.conversation__preview { display: block; color: var(--color-text-maxcontrast); font-size: 13px; }
.conversation__preview--typing { color: var(--color-primary-element); font-style: italic; }
.conversation__badge { min-width: 22px; padding: 2px 6px; border-radius: 999px; color: var(--color-primary-element-text); background: var(--color-primary-element); font-size: 12px; text-align: center; }
</style>
