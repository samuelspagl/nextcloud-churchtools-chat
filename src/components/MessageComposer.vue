<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import { translate as t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, shallowRef, useTemplateRef, watch } from 'vue'
import type { ChatMessage } from '../types/chat'
import ComposerActionsMenu from './ComposerActionsMenu.vue'

const props = defineProps<{ disabled: boolean; replyTo?: ChatMessage | null }>()
const emit = defineEmits<{ send: [body: string]; cancelReply: []; typing: [typing: boolean] }>()

const draft = shallowRef('')
const editor = useTemplateRef<InstanceType<typeof NcRichContenteditable>>('editor')
const canSend = computed(() => !props.disabled && draft.value.trim() !== '')
const sendIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 21 23 12 2 3v7l15 2-15 2v7Z"/></svg>'

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

function submit() {
	const body = draft.value.trim()
	if (!canSend.value) return
	clearTyping()
	emit('send', body)
	draft.value = ''
}

function clearTyping() {
	stopTypingHeartbeat()
	emit('typing', false)
}

function openSmartPicker() {
	editor.value?.showTribute('/')
}
</script>

<template>
	<form class="composer" @submit.prevent="submit">
		<div class="composer__inner">
			<div v-if="replyTo" class="composer__reply">
				<span>{{ t('churchtools_chat', 'Replying to') }} {{ replyTo.senderName || replyTo.sender }}: {{ replyTo.body }}</span>
				<NcButton type="button" variant="tertiary" @click="emit('cancelReply')">{{ t('churchtools_chat', 'Cancel') }}</NcButton>
			</div>
			<div class="composer__controls">
				<ComposerActionsMenu :disabled="disabled" @open-smart-picker="openSmartPicker" />
				<NcRichContenteditable
					ref="editor"
					v-model="draft"
					class="composer__editor"
					:disabled="disabled"
					:maxlength="10000"
					:placeholder="t('churchtools_chat', 'Write a message…')"
					:link-autocomplete="true"
					:emoji-autocomplete="false"
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
