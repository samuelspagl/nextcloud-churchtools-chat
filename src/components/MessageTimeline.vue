<script setup lang="ts">
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { translate as t } from '@nextcloud/l10n'
import { nextTick, onBeforeUnmount, onMounted, shallowRef, watch, useTemplateRef } from 'vue'
import type { ChatMessage } from '../types/chat'
import MessageBubble from './MessageBubble.vue'

const props = defineProps<{
	messages: readonly ChatMessage[]
	currentUserId: string
	loading: boolean
	focusMessageId: string | null
}>()

const emit = defineEmits<{
	retry: [message: ChatMessage]
	reply: [message: ChatMessage]
	react: [message: ChatMessage, emoji: string]
}>()
const timeline = useTemplateRef<HTMLElement>('timeline')
const content = useTemplateRef<HTMLElement>('content')
const stickToBottom = shallowRef(true)
let contentResizeObserver: ResizeObserver | null = null
let previousContentHeight = 0

function scrollToBottom() {
	if (!timeline.value) return
	timeline.value.scrollTop = timeline.value.scrollHeight
	stickToBottom.value = true
}

function updateStickToBottom() {
	if (!timeline.value) return
	const distanceFromBottom = timeline.value.scrollHeight - timeline.value.scrollTop - timeline.value.clientHeight
	stickToBottom.value = distanceFromBottom <= 80
}

watch(() => props.messages.length, async () => {
	await nextTick()
	scrollToBottom()
})

watch(() => props.focusMessageId, async (messageId) => {
	if (!messageId) return
	await nextTick()
	const target = Array.from(timeline.value?.querySelectorAll<HTMLElement>('[data-message-id]') ?? [])
		.find((element) => element.dataset.messageId === messageId)
	target?.scrollIntoView({ block: 'center', behavior: 'smooth' })
})

onMounted(() => {
	if (!content.value) return
	previousContentHeight = content.value.getBoundingClientRect().height
	contentResizeObserver = new ResizeObserver(([entry]) => {
		if (!timeline.value || !entry) return
		const distanceFromPreviousBottom = previousContentHeight - timeline.value.scrollTop - timeline.value.clientHeight
		const wasAtBottomBeforeResize = distanceFromPreviousBottom <= 80
		previousContentHeight = entry.contentRect.height
		if (stickToBottom.value || wasAtBottomBeforeResize) {
			scrollToBottom()
		}
	})
	contentResizeObserver.observe(content.value)
})

onBeforeUnmount(() => {
	contentResizeObserver?.disconnect()
})
</script>

<template>
	<section ref="timeline" class="timeline" aria-label="Messages" aria-live="polite" aria-relevant="additions" @scroll="updateStickToBottom">
		<div ref="content" class="timeline__content">
			<div v-if="loading" class="timeline__state">
				<NcLoadingIcon :size="32" />
				<span>{{ t('churchtools_chat', 'Loading messages…') }}</span>
			</div>
			<p v-else-if="messages.length === 0" class="timeline__state">{{ t('churchtools_chat', 'No messages in this conversation yet.') }}</p>
			<MessageBubble
				v-for="message in messages"
				v-else
				:key="message.id"
				:message="message"
				:current-user-id="currentUserId"
				:focused="message.id === focusMessageId"
				@retry="emit('retry', $event)"
				@reply="emit('reply', $event)"
				@react="(message, emoji) => emit('react', message, emoji)" />
		</div>
	</section>
</template>

<style scoped>
.timeline { width: 100%; min-height: 0; overflow-y: auto; }
.timeline__content { display: flex; width: 100%; min-height: 100%; flex-direction: column; gap: 14px; padding: 24px clamp(16px, 4vw, 56px); }
.timeline__state { margin: auto; color: var(--color-text-maxcontrast); }
</style>
