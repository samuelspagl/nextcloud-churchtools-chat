<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, shallowRef, useTemplateRef, watch } from 'vue'
import { searchPersons } from '../services/chatApi'
import type { ChatMessage } from '../types/chat'
import ComposerActionsMenu from './ComposerActionsMenu.vue'

interface MentionSuggestion {
	id: string
	label: string
	icon: string
	iconUrl: string | null
	source: 'users'
}

const props = defineProps<{ disabled: boolean; replyTo?: ChatMessage | null; editingMessage?: ChatMessage | null }>()
const emit = defineEmits<{ send: [body: string, mentions: string[]]; cancelReply: []; typing: [typing: boolean]; edit: [message: ChatMessage, body: string]; cancelEdit: [] }>()

const draft = shallowRef('')

watch(() => props.editingMessage, (message) => {
	if (message) draft.value = message.body
}, { immediate: true })

function cancelEdit() {
	draft.value = ''
	emit('cancelEdit')
}
const editor = useTemplateRef<InstanceType<typeof NcRichContenteditable>>('editor')
const canSend = computed(() => !props.disabled && draft.value.trim() !== '')
const sendIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 21 23 12 2 3v7l15 2-15 2v7Z"/></svg>'
const smileyIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm-3.5-9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm7 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM12 17.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5Z"/></svg>'

const TYPING_HEARTBEAT_MS = 20_000
let lastHasContent = false
let typingHeartbeat: ReturnType<typeof window.setInterval> | undefined

function stopTypingHeartbeat() {
	if (typingHeartbeat !== undefined) {
		window.clearInterval(typingHeartbeat)
		typingHeartbeat = undefined
	}
}

function reportTyping() {
	if (props.disabled) return
	emit('typing', true)
	stopTypingHeartbeat()
	typingHeartbeat = window.setInterval(() => emit('typing', true), TYPING_HEARTBEAT_MS)
}

watch(draft, () => {
	const hasContent = draft.value.trim() !== ''
	if (hasContent === lastHasContent) return
	lastHasContent = hasContent
	if (hasContent) {
		reportTyping()
	} else {
		stopTypingHeartbeat()
		emit('typing', false)
	}
})

onBeforeUnmount(() => {
	stopTypingHeartbeat()
	emit('typing', false)
})

const mentionUserData = shallowRef<Record<string, MentionSuggestion>>({})
const mentionMatrixIds = new Map<string, string>()

async function autoCompleteMention(search: string, callback: (items: MentionSuggestion[]) => void) {
	const query = search.trim()
	if (props.disabled || query.length < 2) {
		callback([])
		return
	}
	try {
		const response = await searchPersons(query)
		const items: MentionSuggestion[] = response.persons.map((person) => ({
			id: String(person.id),
			label: person.displayName,
			icon: 'icon-user',
			iconUrl: person.imageUrl,
			source: 'users',
		}))
		mentionUserData.value = { ...mentionUserData.value, ...Object.fromEntries(items.map((item) => [item.id, item])) }
		for (const person of response.persons) {
			mentionMatrixIds.set(String(person.id), person.matrixUserId)
		}
		callback(items)
	} catch {
		callback([])
	}
}

function extractMentionedMatrixIds(body: string): string[] {
	const ids = new Set<string>()
	for (const match of body.matchAll(/@(\d+)/g)) {
		const matrixUserId = mentionMatrixIds.get(match[1])
		if (matrixUserId) ids.add(matrixUserId)
	}
	return [...ids]
}

function submit() {
	const body = draft.value.trim()
	if (!canSend.value) return
	clearTyping()
	if (props.editingMessage) {
		emit('edit', props.editingMessage, body)
	} else {
		emit('send', body, extractMentionedMatrixIds(body))
	}
	draft.value = ''
}

function clearTyping() {
	stopTypingHeartbeat()
	emit('typing', false)
}

function openSmartPicker() {
	editor.value?.showTribute('/')
}

let savedRange: Range | null = null

function editorElement(): HTMLElement | null {
	const el = editor.value?.$el as HTMLElement | undefined
	return el?.querySelector<HTMLElement>('[contenteditable]') ?? null
}

function onEmojiTriggerMousedown(event: MouseEvent) {
	const editable = editorElement()
	const selection = window.getSelection()
	if (editable && selection && selection.rangeCount > 0) {
		const range = selection.getRangeAt(0)
		if (editable.contains(range.commonAncestorContainer)) {
			savedRange = range.cloneRange()
		}
	}
	// Keep the editor focused so its selection survives opening the picker.
	event.preventDefault()
}

function insertEmoji(emoji: string) {
	const editable = editorElement()
	if (editable && savedRange) {
		editable.focus()
		const selection = window.getSelection()
		if (selection) {
			selection.removeAllRanges()
			selection.addRange(savedRange)
		}
		savedRange = null
		let inserted = false
		try {
			inserted = document.execCommand('insertText', false, emoji)
		} catch {
			// execCommand is not available everywhere (e.g. tests).
		}
		if (inserted) {
			// The picker refocuses its trigger on close; keep the editor focused for typing.
			window.setTimeout(() => editorElement()?.focus(), 0)
			return
		}
	}
	draft.value += emoji
}
</script>

<template>
	<form class="composer" @submit.prevent="submit">
		<div class="composer__inner">
			<div v-if="editingMessage" class="composer__reply">
				<span>{{ t('churchtools_chat', 'Editing message') }}</span>
				<NcButton type="button" variant="tertiary" @click="cancelEdit">{{ t('churchtools_chat', 'Cancel') }}</NcButton>
			</div>
			<div v-else-if="replyTo" class="composer__reply">
				<span>{{ t('churchtools_chat', 'Replying to') }} {{ replyTo.senderName || replyTo.sender }}: {{ replyTo.body }}</span>
				<NcButton type="button" variant="tertiary" @click="emit('cancelReply')">{{ t('churchtools_chat', 'Cancel') }}</NcButton>
			</div>
			<div class="composer__controls">
				<ComposerActionsMenu :disabled="disabled" @open-smart-picker="openSmartPicker" />
				<NcEmojiPicker :close-on-select="true" @select="insertEmoji">
					<NcButton
						variant="tertiary"
						:disabled="disabled"
						:aria-label="t('churchtools_chat', 'Insert emoji')"
						:title="t('churchtools_chat', 'Insert emoji')"
						@mousedown="onEmojiTriggerMousedown">
						<template #icon>
							<NcIconSvgWrapper :svg="smileyIcon" :size="24" />
						</template>
					</NcButton>
				</NcEmojiPicker>
				<NcRichContenteditable
					ref="editor"
					v-model="draft"
					class="composer__editor"
					:disabled="disabled"
					:maxlength="10000"
					:placeholder="t('churchtools_chat', 'Write a message…')"
					:link-autocomplete="true"
					:emoji-autocomplete="false"
					:auto-complete="autoCompleteMention"
					:user-data="mentionUserData"
					@submit="submit" />
				<NcButton
					class="composer__send"
					type="submit"
					variant="tertiary"
					:aria-label="t('churchtools_chat', 'Send message')"
					:title="t('churchtools_chat', 'Send message')"
					:disabled="!canSend">
					<template #icon>
						<NcIconSvgWrapper :svg="sendIcon" :size="24" />
					</template>
				</NcButton>
			</div>
		</div>
	</form>
</template>

<style scoped>
.composer { flex: 0 0 auto; padding: 8px clamp(8px, 3vw, 32px) max(8px, env(safe-area-inset-bottom)); border-top: 1px solid var(--color-border); background: var(--color-main-background); }
.composer__inner { display: grid; width: min(100%, 1114px); min-width: 0; margin-inline: auto; gap: 8px; }
.composer__reply { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px; border-inline-start: 3px solid var(--color-primary-element); border-radius: var(--border-radius-large); background: var(--color-background-dark); }
.composer__reply span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.composer__controls { display: flex; min-width: 0; align-items: flex-end; gap: 4px; }
.composer__editor { min-width: 0; min-height: var(--default-clickable-area); flex: 1; }
.composer__send { flex: 0 0 auto; }
@media (max-width: 480px) {
	.composer { padding-inline: 4px; }
	.composer__controls { gap: 2px; }
}
</style>
