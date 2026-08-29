<script setup lang="ts">
import { showError, showWarning } from '@nextcloud/dialogs'
import NcContent from '@nextcloud/vue/components/NcContent'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, shallowRef } from 'vue'
import AttachmentDropzone from './components/AttachmentDropzone.vue'
import ConversationSidebar from './components/ConversationSidebar.vue'
import ConversationDetailsSidebar from './components/ConversationDetailsSidebar.vue'
import ConversationHeader from './components/ConversationHeader.vue'
import MessageComposer from './components/MessageComposer.vue'
import MessageTimeline from './components/MessageTimeline.vue'
import PersonSearch from './components/PersonSearch.vue'
import { getErrorMessage } from './services/chatApi'
import { useChat } from './composables/useChat'
import { MAX_ATTACHMENT_BYTES } from './utils/attachments'
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
	replyTargets,
	selectRoom,
	searchPersons,
	clearPersonSearch,
	searchConversations,
	clearConversationSearch,
	searchMessages,
	focusMessage,
	startDirectChat,
	send,
	sendFile,
	setTyping,
	retry,
	react,
	unreact,
	deleteMessage,
	loadOlderMessages,
	toggleDetails,
	closeDetails,
} = useChat()

const searchQuery = shallowRef('')
const personQuery = shallowRef('')
const messageSearchQuery = shallowRef('')
const personSearchOpen = shallowRef(false)
const sidebarOpen = shallowRef(true)
const replyTarget = shallowRef<ChatMessage | null>(null)
const pendingFiles = shallowRef<File[]>([])
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

const attachmentsDisabled = computed(() => !activeRoom.value || !status.value?.capabilities.send || activeRoom.value.encrypted)

function addPendingFiles(files: FileList) {
	const accepted: File[] = []
	for (const file of Array.from(files)) {
		if (file.size > MAX_ATTACHMENT_BYTES) {
			showError(t('churchtools_chat', '{filename} is too large to send.', { filename: file.name }))
			continue
		}
		accepted.push(file)
	}
	if (accepted.length > 0) pendingFiles.value = [...pendingFiles.value, ...accepted]
}

function removePendingFile(file: File) {
	pendingFiles.value = pendingFiles.value.filter((pending) => pending !== file)
}

async function sendPendingFiles(files: File[]) {
	pendingFiles.value = []
	for (const file of files) {
		try {
			await sendFile(file)
		} catch (caught) {
			if (caught instanceof Error && caught.message === 'attachment_too_large') {
				showError(t('churchtools_chat', '{filename} is too large to send.', { filename: file.name }))
			} else {
				showError(getErrorMessage(caught))
			}
		}
	}
}

async function reactToMessage(message: ChatMessage, emoji: string) {
	try {
		await react(message, emoji)
	} catch {
		// The next sync reconciles reactions if the optimistic update cannot be applied.
	}
}

async function unreactToMessage(message: ChatMessage, emoji: string) {
	try {
		await unreact(message, emoji)
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
				<div class="chat-pane__header">
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
				</div>
				<div class="chat-pane__body">
					<AttachmentDropzone :disabled="attachmentsDisabled" @files-dropped="addPendingFiles">
						<div v-if="connectionNotice || error" class="connection-state" role="status">
							<h2>{{ t('churchtools_chat', 'ChurchTools Chat is not ready') }}</h2>
							<p>{{ connectionNotice || error }}</p>
							<a :href="settingsUrl">{{ t('churchtools_chat', 'Open Personal settings') }}</a>
						</div>
						<div v-else-if="activeRoom?.encrypted" class="connection-state" role="status">
							<h2>{{ t('churchtools_chat', 'This room is encrypted') }}</h2>
							<p>{{ t('churchtools_chat', 'End-to-end encrypted Matrix events cannot be processed by the server-side gateway. Encrypted event content is not decrypted or displayed.') }}</p>
						</div>
						<MessageTimeline
							v-else-if="activeRoom"
							:messages="messages"
							:current-user-id="status?.matrixUserId || ''"
							:loading="loadingMessages"
							:has-more="activeRoom?.hasMore ?? false"
							:focus-message-id="focusedMessageId"
							:reply-targets="replyTargets"
							:typing-users="activeRoom?.typingUsers ?? []"
							:read-receipts="activeRoom?.kind === 'direct' ? activeRoom?.readReceipts : undefined"
							@load-older="loadOlderMessages(activeRoomId ?? '')"
							@retry="retryMessage"
							@reply="replyTarget = $event"
							@react="reactToMessage"
							@unreact="unreactToMessage"
							@delete="deleteMessage"
							@jump="focusMessage" />
						<div v-else-if="!loading" class="connection-state">
							<h2>{{ t('churchtools_chat', 'Select a conversation') }}</h2>
							<p>{{ t('churchtools_chat', 'Choose a ChurchTools room from the conversation list.') }}</p>
						</div>
					</AttachmentDropzone>
				</div>
				<MessageComposer
					v-if="activeRoom && !(connectionNotice || error)"
					:disabled="!status?.capabilities.send || activeRoom.encrypted"
					:reply-to="replyTarget"
					:pending-files="pendingFiles"
					@typing="setTyping"
					@cancel-reply="replyTarget = null"
					@send="sendMessage"
					@send-files="sendPendingFiles"
					@files-selected="addPendingFiles"
					@remove-pending-file="removePendingFile" />
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
.chat-app { min-width: 0; min-height: 0; block-size: var(--body-height); max-block-size: var(--body-height); overflow: hidden; }
.chat-layout { display: grid; min-width: 0; min-height: 0; block-size: 100%; flex: 1; grid-template-columns: minmax(280px, 340px) minmax(0, 1fr); overflow: hidden; }
.chat-layout > :first-child { border-inline-end: 1px solid var(--color-border); }
.chat-pane { display: grid; min-width: 0; min-height: 0; block-size: 100%; grid-template-rows: auto minmax(0, 1fr) auto; overflow: hidden; background: var(--color-main-background); }
.chat-pane__header { min-width: 0; min-height: 0; }
.chat-pane__body { display: flex; min-width: 0; min-height: 0; overflow: hidden; }
.chat-pane__body > :first-child { min-width: 0; min-height: 0; flex: 1; }
.connection-state { max-width: 560px; margin: auto; padding: 24px; text-align: center; }
.session-expired { display: flex; flex-wrap: wrap; gap: 4px 12px; align-items: center; padding: 10px 16px; background: var(--color-error); color: var(--color-primary-element-text); font-size: 0.9em; }
.session-expired a { color: inherit; font-weight: 600; text-decoration: underline; }
.connection-state h2 { margin-block: 0 8px; }
@media (max-width: 639px) {
	.chat-layout { display: block; block-size: 100%; }
	.chat-layout > :first-child, .chat-pane { width: 100%; block-size: 100%; }
	.chat-layout:not(.chat-layout--sidebar-hidden) .chat-pane { display: none; }
	.chat-layout--sidebar-hidden > :first-child { display: none; }
}
</style>
