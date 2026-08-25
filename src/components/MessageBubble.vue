<script setup lang="ts">
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import { FilePickerClosed, getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { useFormatTime } from '@nextcloud/vue/composables/useFormatDateTime'
import { computed, shallowRef } from 'vue'
import { getErrorMessage, saveAttachment } from '../services/chatApi'
import type { ChatMessage } from '../types/chat'
import { displayableAvatarUrl } from '../utils/avatar'
import { attachmentDownloadUrl } from '../utils/attachments'
import { messageSenderLabel } from '../utils/messages'
import { getReplyFallbackQuote, isReplyMessage } from '../utils/relations'
import MessageReferencePreview from './MessageReferencePreview.vue'
import MessageReferencePreviewControls from './MessageReferencePreviewControls.vue'
import MessageAttachment from './MessageAttachment.vue'
import ReplyPreview from './ReplyPreview.vue'

const props = defineProps<{
	message: ChatMessage
	currentUserId: string
	grouped?: boolean
	focused?: boolean
	replyToMessage?: ChatMessage | null
	canJumpReply?: boolean
}>()

const emit = defineEmits<{
	retry: [message: ChatMessage]
	reply: [message: ChatMessage]
	react: [message: ChatMessage, emoji: string]
	delete: [message: ChatMessage]
	jump: []
}>()

const isDeleted = computed(() => props.message.redacted === true || props.message.body === '')
const canDelete = computed(() => isOwn.value && props.message.status === 'sent' && !isDeleted.value)
const isOwn = computed(() => props.message.sender === props.currentUserId)
const senderLabel = computed(() => messageSenderLabel(props.message, props.currentUserId, t('churchtools_chat', 'You')))
const hasReply = computed(() => isReplyMessage(props.message))
const replyFallbackText = computed(() =>
	hasReply.value ? getReplyFallbackQuote(props.message) : null)
const savingAttachment = shallowRef(false)

const formattedTime = computed(() => new Intl.DateTimeFormat(undefined, {
	hour: '2-digit',
	minute: '2-digit',
}).format(props.message.timestamp))

const formattedFullTimestamp = useFormatTime(() => props.message.timestamp, () => ({ format: { dateStyle: 'medium', timeStyle: 'short' } }))

const replyIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7V3l-7 7 7 7v-4.1c5 0 8.5 1.6 11 5.1-.8-5-3.8-10-11-11Z"/></svg>'
const saveIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h12l4 4v14H5V3Zm2 2v14h12V8.1L16.9 6H7Zm2 0h6v5H9V5Zm1 8v4h4v-4h-4Z"/></svg>'
const downloadIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 3h2v10.2l3.6-3.6L18 11l-6 6-6-6 1.4-1.4 3.6 3.6V3ZM5 19h14v2H5v-2Z"/></svg>'
const deleteIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3v1H4v2h16V4h-5V3H9Zm-3 5 1 12h10l1-12H6Z"/></svg>'

function downloadAttachment() {
	const attachment = props.message.attachment
	if (attachment) window.location.assign(attachmentDownloadUrl(attachment.mxcUrl, attachment.filename))
}

async function saveToNextcloud() {
	const attachment = props.message.attachment
	if (!attachment || savingAttachment.value) return
	try {
		const picker = getFilePickerBuilder(t('churchtools_chat', 'Choose a folder'))
			.setMultiSelect(false)
			.allowDirectories(true)
			.setCanPick((node) => node.type === 'folder')
			.setType(1)
			.build()
		const path = await picker.pick()
		if (typeof path !== 'string') return
		savingAttachment.value = true
		const result = await saveAttachment(attachment.mxcUrl, path.replace(/^\/+/, ''), attachment.filename)
		showSuccess(t('churchtools_chat', 'Saved to {path}', { path: result.path }))
	} catch (error) {
		if (!(error instanceof FilePickerClosed)) showError(getErrorMessage(error))
	} finally {
		savingAttachment.value = false
	}
}
</script>

<template>
	<article class="message" :class="{ 'message--own': isOwn, 'message--grouped': grouped, 'message--focused': focused, 'message--mention': message.mentionsMe }" :data-message-id="message.id" :title="formattedFullTimestamp" tabindex="-1">
		<MessageReferencePreview
			:message-id="message.id"
			:text="message.body"
			:is-own="isOwn"
			v-slot="{ preview }">
			<div class="message__row">
				<NcAvatar
					v-if="!grouped"
					:url="displayableAvatarUrl(message.senderAvatarUrl)"
					:display-name="senderLabel"
					:is-no-user="true"
					:disable-menu="true"
					:size="32" />
				<div class="message__content">
					<header v-if="!grouped" class="message__metadata">
						<strong>{{ senderLabel }}</strong>
						<time :datetime="new Date(message.timestamp).toISOString()">{{ formattedTime }}</time>
						<span v-if="message.edited">{{ t('churchtools_chat', 'edited') }}</span>
					</header>
					<div class="message__bubble-line">
						<div class="message__bubble" :class="{ 'message__bubble--attachment': message.attachment, 'message__bubble--deleted': isDeleted }">
							<ReplyPreview
								v-if="hasReply && !isDeleted"
								:message="replyToMessage ?? null"
								:fallback-text="replyFallbackText"
								:current-user-id="currentUserId"
								:can-jump="canJumpReply"
								@jump="emit('jump')" />
							<span v-if="isDeleted">{{ t('churchtools_chat', 'Message deleted') }}</span>
							<MessageAttachment v-else-if="message.attachment" :attachment="message.attachment" :saving="savingAttachment" @save="saveToNextcloud" />
							<NcRichText v-else :text="message.body" autolink use-markdown />
						</div>
						<MessageReferencePreviewControls :preview="preview" />
						<div v-if="message.status !== 'sending'" class="message__actions" :aria-label="t('churchtools_chat', 'Message actions')">
							<NcButton
								variant="tertiary"
								:aria-label="t('churchtools_chat', 'Reply')"
								:title="t('churchtools_chat', 'Reply')"
								@click="emit('reply', message)">
								<template #icon>
									<NcIconSvgWrapper :svg="replyIcon" />
								</template>
							</NcButton>
							<NcButton
								v-if="message.attachment"
								variant="tertiary"
								:disabled="savingAttachment"
								:aria-label="t('churchtools_chat', 'Save to Nextcloud')"
								:title="t('churchtools_chat', 'Save to Nextcloud')"
								@click="saveToNextcloud">
								<template #icon><NcIconSvgWrapper :svg="saveIcon" /></template>
							</NcButton>
							<NcButton
								v-if="message.attachment"
								variant="tertiary"
								:aria-label="t('churchtools_chat', 'Download attachment')"
								:title="t('churchtools_chat', 'Download attachment')"
								@click="downloadAttachment">
								<template #icon><NcIconSvgWrapper :svg="downloadIcon" /></template>
							</NcButton>
							<NcButton
								variant="tertiary"
								:aria-label="t('churchtools_chat', 'React with thumbs up')"
								:title="t('churchtools_chat', 'React with thumbs up')"
								@click="emit('react', message, '👍')">
								<span aria-hidden="true">👍</span>
							</NcButton>
							<NcButton
								v-if="canDelete"
								variant="tertiary"
								:aria-label="t('churchtools_chat', 'Delete message')"
								:title="t('churchtools_chat', 'Delete message')"
								@click="emit('delete', message)">
								<template #icon><NcIconSvgWrapper :svg="deleteIcon" /></template>
							</NcButton>
						</div>
					</div>
					<div v-if="message.reactions && Object.keys(message.reactions).length > 0" class="message__reactions">
						<span v-for="(count, emoji) in message.reactions" :key="emoji">{{ emoji }} {{ count }}</span>
					</div>
					<div v-if="message.status" class="message__status" role="status">
						<span v-if="message.status === 'sending'">{{ t('churchtools_chat', 'Sending…') }}</span>
						<span v-else-if="message.status === 'failed'">{{ t('churchtools_chat', 'Not sent') }}</span>
						<NcButton v-if="message.status === 'failed'" variant="tertiary" @click="emit('retry', message)">
							{{ t('churchtools_chat', 'Retry') }}
						</NcButton>
					</div>
				</div>
			</div>
		</MessageReferencePreview>
	</article>
</template>

<style scoped>
.message { display: flex; width: 100%; min-width: 0; flex-direction: column; gap: 4px; }
.message--grouped { margin-block-start: -8px; }
.message--focused .message__bubble { box-shadow: 0 0 0 3px var(--color-primary-element-light); }
.message--mention .message__bubble { box-shadow: inset 3px 0 0 var(--color-primary-element); }
.message--own.message--mention .message__bubble { box-shadow: inset -3px 0 0 var(--color-primary-element-text); }
.message__bubble--deleted { color: var(--color-text-maxcontrast); font-style: italic; }
.message__row { display: flex; min-width: 0; align-items: flex-start; gap: 10px; max-width: min(850px, 96%); align-self: flex-start; }
.message--own .message__row { align-self: flex-end; flex-direction: row-reverse; }
.message__content { min-width: 0; }
.message__metadata { display: flex; align-items: baseline; gap: 8px; margin-block-end: 3px; font-size: 13px; }
.message--own .message__metadata { justify-content: flex-end; }
.message__metadata time, .message__metadata span { color: var(--color-text-maxcontrast); font-size: 12px; }
.message__bubble-line { position: relative; display: flex; min-width: 0; align-items: center; gap: 4px; }
.message--own .message__bubble-line { flex-direction: row-reverse; }
.message__bubble { padding: 8px 12px; border-radius: 4px 16px 16px 16px; background: var(--color-background-dark); overflow-wrap: anywhere; }
.message--own .message__bubble { border-radius: 16px 4px 16px 16px; color: var(--color-primary-element-text); background: var(--color-primary-element); }
.message__bubble--attachment { padding: 0; overflow: visible; background: transparent; }
.message--own .message__bubble--attachment { background: transparent; }
.message__status { display: flex; align-items: center; justify-content: flex-end; gap: 6px; color: var(--color-text-maxcontrast); font-size: 12px; }
.message__actions {
	position: absolute;
	z-index: 4;
	inset-block-end: calc(100% + 4px);
	inset-inline-end: 0;
	display: flex;
	padding: 2px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: 12px;
	background: var(--color-main-background);
	box-shadow: 0 2px 8px var(--color-box-shadow);
	opacity: 0;
	pointer-events: none;
	transform: translateY(4px);
	transition: opacity var(--animation-quick), transform var(--animation-quick);
}
.message--own .message__actions { inset-inline: 0 auto; }
.message__row:hover .message__actions,
.message__row:focus-within .message__actions {
	opacity: 1;
	pointer-events: auto;
	transform: translateY(0);
}
.message__reactions { display: flex; flex-wrap: wrap; gap: 4px; margin-block-start: 4px; }
.message__reactions span { padding: 2px 7px; border: 1px solid var(--color-border); border-radius: 999px; background: var(--color-main-background); font-size: 12px; }
@media (hover: none) {
	.message__actions {
		position: static;
		opacity: 1;
		pointer-events: auto;
		transform: none;
	}
}
</style>
