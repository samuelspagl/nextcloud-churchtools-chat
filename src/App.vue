<script setup lang="ts">
import { showWarning } from '@nextcloud/dialogs'
import NcContent from '@nextcloud/vue/components/NcContent'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, shallowRef } from 'vue'
import ConversationSidebar from './components/ConversationSidebar.vue'
import ConversationDetailsSidebar from './components/ConversationDetailsSidebar.vue'
import ConversationHeader from './components/ConversationHeader.vue'
import MessageComposer from './components/MessageComposer.vue'
import MessageTimeline from './components/MessageTimeline.vue'
import PersonSearch from './components/PersonSearch.vue'
import { useChat } from './composables/useChat'
import type { ChatMessage, PersonSearchResult } from './types/chat'

const {
	status,
	sessionExpired,
	rooms,
	activeRoomId,
	activeRoom,
	messages,
	loading,
	loadingMessages,
	detailsOpen,
	roomDetails,
	loadingRoomDetails,
	roomDetailsError,
	error,
	personResults,
	searchingPersons,
	personSearchError,
	startingPersonId,
	conversationSearchResults,
	searchingConversations,
	conversationSearchError,
	messageSearchResults,
	searchingMessages,
	messageSearchError,
	focusedMessageId,
	selectRoom,
	searchPersons,
	clearPersonSearch,
	searchConversations,
	clearConversationSearch,
	searchMessages,
	focusMessage,
	startDirectChat,
	send,
	retry,
	react,
	toggleDetails,
	closeDetails,
} = useChat()

const searchQuery = shallowRef('')
const personQuery = shallowRef('')
const messageSearchQuery = shallowRef('')
const personSearchOpen = shallowRef(false)
const sidebarOpen = shallowRef(true)
const replyTarget = shallowRef<ChatMessage | null>(null)
const settingsUrl = generateUrl('/settings/user/additional')
const connectionNotice = computed(() => {
	if (!status.value) return ''
	if (!status.value.configured) return t('churchtools_chat', 'Connect your ChurchTools account in Personal settings to use chat.')
	if (!status.value.matrixConnected) return t('churchtools_chat', 'Your ChurchTools token is valid. Add the CT Chat password in Personal settings to connect messages.')
	return ''
})

async function chooseRoom(roomId: string) {
	messageSearchQuery.value = ''
	searchQuery.value = ''
	clearConversationSearch()
	await selectRoom(roomId)
	if (window.matchMedia('(max-width: 639px)').matches) sidebarOpen.value = false
}

function updateConversationSearch(query: string) {
	searchQuery.value = query
	searchConversations(query)
}

async function chooseConversationSearchResult(result: { roomId: string; message?: ChatMessage }) {
	searchQuery.value = ''
	clearConversationSearch()
	await selectRoom(result.roomId)
	if (result.message) focusMessage(result.message)
	if (window.matchMedia('(max-width: 639px)').matches) sidebarOpen.value = false
}

function showConversationList() {
	closeConversationDetails()
	sidebarOpen.value = true
}

function closeConversationDetails() {
	messageSearchQuery.value = ''
	closeDetails()
}

function updateMessageSearch(query: string) {
	messageSearchQuery.value = query
	searchMessages(query)
}

function openPersonSearch() {
	personQuery.value = ''
	clearPersonSearch()
	personSearchOpen.value = true
}

function closePersonSearch() {
	personSearchOpen.value = false
	personQuery.value = ''
	clearPersonSearch()
}

async function choosePerson(person: PersonSearchResult) {
	try {
		const directChat = await startDirectChat(person)
		if (!directChat.canChat) {
			showWarning(t('churchtools_chat', 'ChurchTools reports canChat=false for this person. Matrix will still try to open the conversation.'))
		}
		personSearchOpen.value = false
		personQuery.value = ''
		if (window.matchMedia('(max-width: 639px)').matches) sidebarOpen.value = false
	} catch {
		// The person search panel keeps the error visible for correction or retry.
	}
}

async function sendMessage(body: string) {
	try {
		await send(body, { replyTo: replyTarget.value ?? undefined })
		replyTarget.value = null
	} catch {
		// The optimistic message exposes retry in the timeline.
	}
}

async function reactToMessage(message: ChatMessage, emoji: string) {
	try {
		await react(message, emoji)
	} catch {
		// The next sync reconciles reactions if the optimistic update cannot be applied.
	}
}

async function retryMessage(message: ChatMessage) {
	try {
		await retry(message)
	} catch {
		// Keep the message in failed state if retry fails.
	}
}
</script>

<template>
	<NcContent app-name="churchtools_chat" class="chat-app">
		<div class="chat-layout" :class="{ 'chat-layout--sidebar-hidden': !sidebarOpen }">
			<PersonSearch
				v-if="personSearchOpen"
				v-model:query="personQuery"
				:results="personResults"
				:loading="searchingPersons"
				:error="personSearchError"
				:starting-person-id="startingPersonId"
				@close="closePersonSearch"
				@search="searchPersons"
				@select="choosePerson" />
			<ConversationSidebar
				v-else
				:query="searchQuery"
				:rooms="rooms"
				:active-room-id="activeRoomId"
				:loading="loading"
				:can-start-chat="status?.capabilities.directChat ?? false"
				:search-results="conversationSearchResults"
				:searching="searchingConversations"
				:search-error="conversationSearchError"
				@select="chooseRoom"
				@update:query="updateConversationSearch"
				@select-search-result="chooseConversationSearchResult"
				@new-chat="openPersonSearch" />

			<main class="chat-pane">
				<div v-if="sessionExpired" class="session-expired" role="alert">
					<span>{{ t('churchtools_chat', 'Your chat session expired. Reconnect in Personal settings to keep receiving messages.') }}</span>
					<a :href="settingsUrl">{{ t('churchtools_chat', 'Open Personal settings') }}</a>
				</div>
				<ConversationHeader
					v-if="activeRoom"
					:room="activeRoom"
					:details-open="detailsOpen"
					@back="showConversationList"
					@toggle-details="toggleDetails" />

				<div v-if="connectionNotice || error" class="connection-state" role="status">
					<h2>{{ t('churchtools_chat', 'ChurchTools Chat is not ready') }}</h2>
					<p>{{ connectionNotice || error }}</p>
					<a :href="settingsUrl">{{ t('churchtools_chat', 'Open Personal settings') }}</a>
				</div>
				<template v-else-if="activeRoom">
					<div v-if="activeRoom.encrypted" class="connection-state" role="status">
						<h2>{{ t('churchtools_chat', 'This room is encrypted') }}</h2>
						<p>{{ t('churchtools_chat', 'End-to-end encrypted Matrix events cannot be processed by the server-side gateway. Encrypted event content is not decrypted or displayed.') }}</p>
					</div>
					<MessageTimeline
						v-else
						:messages="messages"
						:current-user-id="status?.matrixUserId || ''"
						:loading="loadingMessages"
						:focus-message-id="focusedMessageId"
						@retry="retryMessage"
						@reply="replyTarget = $event"
						@react="reactToMessage" />
					<MessageComposer
						:disabled="!status?.capabilities.send || activeRoom.encrypted"
						:reply-to="replyTarget"
						@cancel-reply="replyTarget = null"
						@send="sendMessage" />
				</template>
				<div v-else-if="!loading" class="connection-state">
					<h2>{{ t('churchtools_chat', 'Select a conversation') }}</h2>
					<p>{{ t('churchtools_chat', 'Choose a ChurchTools room from the conversation list.') }}</p>
				</div>
			</main>
		</div>
		<ConversationDetailsSidebar
			v-if="activeRoom"
			:open="detailsOpen"
			:room="activeRoom"
			:details="roomDetails"
			:loading="loadingRoomDetails"
			:error="roomDetailsError"
			:search-query="messageSearchQuery"
			:search-results="messageSearchResults"
			:searching="searchingMessages"
			:search-error="messageSearchError"
			@close="closeConversationDetails"
			@update:search-query="updateMessageSearch"
			@focus-message="focusMessage" />
	</NcContent>
</template>

<style scoped>
.chat-layout { display: grid; min-width: 0; min-height: 0; flex: 1; grid-template-columns: minmax(280px, 340px) minmax(0, 1fr); overflow: hidden; }
.chat-layout > :first-child { border-inline-end: 1px solid var(--color-border); }
.chat-pane { display: grid; min-width: 0; min-height: 0; grid-template-rows: auto minmax(0, 1fr) auto; background: var(--color-main-background); }
.connection-state { max-width: 560px; margin: auto; padding: 24px; text-align: center; }
.session-expired { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: center; padding: 10px 16px; background: var(--color-error); color: var(--color-primary-element-text); font-size: 0.9em; }
.session-expired a { color: inherit; font-weight: 600; text-decoration: underline; }
.connection-state h2 { margin-block: 0 8px; }
@media (max-width: 639px) {
	.chat-layout { display: block; }
	.chat-layout > :first-child, .chat-pane { width: 100%; height: 100%; }
	.chat-layout:not(.chat-layout--sidebar-hidden) .chat-pane { display: none; }
	.chat-layout--sidebar-hidden > :first-child { display: none; }
}
</style>
