<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import type { ChatMessage } from '../types/chat'
import { messageSenderLabel } from '../utils/messages'

const props = defineProps<{
	message: ChatMessage | null
	fallbackText?: string | null
	currentUserId: string
	canJump?: boolean
}>()

const emit = defineEmits<{ jump: [] }>()

const isDeleted = computed(() => props.message !== null && (props.message.redacted === true || props.message.body === ''))
const senderLabel = computed(() => props.message === null
	? ''
	: messageSenderLabel(props.message, props.currentUserId, t('churchtools_chat', 'You')))
const excerpt = computed(() => {
	if (props.message !== null) {
		if (isDeleted.value) return t('churchtools_chat', 'Message deleted')
		return props.message.attachment ? props.message.attachment.filename : props.message.body
	}
	if (props.fallbackText) return props.fallbackText
	return t('churchtools_chat', 'Replying to a message')
})
</script>

<template>
	<button
		v-if="canJump && message"
		type="button"
		class="reply-preview"
		:class="{ 'reply-preview--deleted': isDeleted }"
		:title="t('churchtools_chat', 'Jump to the original message')"
		@click="emit('jump')">
		<span class="reply-preview__sender">{{ senderLabel }}</span>
		<blockquote class="reply-preview__quote">{{ excerpt }}</blockquote>
	</button>
	<div v-else class="reply-preview reply-preview--static" :class="{ 'reply-preview--deleted': isDeleted }">
		<span v-if="message" class="reply-preview__sender">{{ senderLabel }}</span>
		<blockquote class="reply-preview__quote">{{ excerpt }}</blockquote>
	</div>
</template>

<style scoped>
.reply-preview {
	display: flex;
	width: 100%;
	min-width: 0;
	flex-direction: column;
	gap: 2px;
	margin-block-end: 6px;
	padding: 4px 10px;
	border: none;
	border-inline-start: 3px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	color: var(--color-main-text);
	text-align: start;
	overflow: hidden;
	cursor: pointer;
}
.reply-preview--static { cursor: default; }
.reply-preview--deleted { color: var(--color-text-maxcontrast); font-style: italic; }
.reply-preview__sender { color: var(--color-text-maxcontrast); font-size: 12px; font-weight: 600; }
.reply-preview__quote {
	display: -webkit-box;
	margin: 0;
	-webkit-box-orient: vertical;
	-webkit-line-clamp: 2;
	overflow: hidden;
	font-size: 13px;
	white-space: normal;
}
</style>