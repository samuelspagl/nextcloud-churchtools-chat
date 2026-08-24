<script setup lang="ts">
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { translate as t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, watch, useTemplateRef } from 'vue'
import type { ChatMessage } from '../types/chat'
import MessageBubble from './MessageBubble.vue'

interface DecoratedMessage extends ChatMessage {
	showDateSeparator: boolean
	grouped: boolean
}

const props = defineProps<{
	messages: readonly ChatMessage[]
	currentUserId: string
	loading: boolean
	hasMore: boolean
	focusMessageId: string | null
}>()

const DAY_MS = 86_400_000

function startOfDay(timestamp: number): number {
	const date = new Date(timestamp)
	date.setHours(0, 0, 0, 0)
	return date.getTime()
}

const dayFormatter = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long' })
const today = startOfDay(Date.now())
const yesterday = today - DAY_MS

function dayLabel(timestamp: number): string {
	const day = startOfDay(timestamp)
	if (day === today) return t('churchtools_chat', 'Today')
	if (day === yesterday) return t('churchtools_chat', 'Yesterday')
	return dayFormatter.format(timestamp)
}

const decoratedMessages = computed<DecoratedMessage[]>(() => {
	const result: DecoratedMessage[] = []
	let previous: ChatMessage | null = null
	for (const message of props.messages) {
		const showDateSeparator = previous === null || startOfDay(previous.timestamp) !== startOfDay(message.timestamp)
		const grouped = !showDateSeparator
			&& previous !== null
			&& previous.sender === message.sender
			&& message.timestamp - previous.timestamp < 5 * 60 * 1000
		result.push({ ...message, showDateSeparator, grouped })
		previous = message
	}
	return result
})

const emit = defineEmits<{
	retry: [message: ChatMessage]
	reply: [message: ChatMessage]
	react: [message: ChatMessage, emoji: string]
	delete: [message: ChatMessage]
	loadOlder: []
}>()
const timeline = useTemplateRef<HTMLElement>('timeline')
const content = useTemplateRef<HTMLElement>('content')
const stickToBottom = shallowRef(true)
let contentResizeObserver: ResizeObserver | null = null
let resizePreviousHeight = 0
let previousFirstId: string | null = null
let watchPreviousHeight = 0

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

watch(() => props.messages, async () => {
	await nextTick()
	const el = timeline.value
	if (!el) return
	const firstId = props.messages[0]?.id ?? null
	const prepended = previousFirstId !== null && firstId !== previousFirstId
	if (prepended && watchPreviousHeight > 0) {
		el.scrollTop = el.scrollTop + (el.scrollHeight - watchPreviousHeight)
	} else if (stickToBottom.value) {
		scrollToBottom()
	}
	previousFirstId = firstId
	watchPreviousHeight = el.scrollHeight
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
	resizePreviousHeight = content.value.getBoundingClientRect().height
	watchPreviousHeight = resizePreviousHeight
	previousFirstId = props.messages[0]?.id ?? null
	contentResizeObserver = new ResizeObserver(([entry]) => {
		if (!timeline.value || !entry) return
		const distanceFromPreviousBottom = resizePreviousHeight - timeline.value.scrollTop - timeline.value.clientHeight
		const wasAtBottomBeforeResize = distanceFromPreviousBottom <= 80
		resizePreviousHeight = entry.contentRect.height
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
		<button v-if="hasMore" type="button" class="timeline__load-older" :disabled="loading" @click="emit('loadOlder')">
			<NcLoadingIcon v-if="loading" :size="16" />
			<span>{{ t('churchtools_chat', 'Load older messages') }}</span>
		</button>
		<div ref="content" class="timeline__content">
			<div v-if="loading" class="timeline__state">
				<NcLoadingIcon :size="32" />
				<span>{{ t('churchtools_chat', 'Loading messages…') }}</span>
			</div>
			<p v-else-if="messages.length === 0" class="timeline__state">{{ t('churchtools_chat', 'No messages in this conversation yet.') }}</p>
			<template v-else>
				<template v-for="message in decoratedMessages" :key="message.id">
					<div v-if="message.showDateSeparator" class="timeline__day" role="separator">
						<span>{{ dayLabel(message.timestamp) }}</span>
					</div>
					<MessageBubble
						:message="message"
						:current-user-id="currentUserId"
						:grouped="message.grouped"
						:focused="message.id === focusMessageId"
						@retry="emit('retry', $event)"
						@reply="emit('reply', $event)"
						@react="(message, emoji) => emit('react', message, emoji)"
						@delete="emit('delete', $event)" />
				</template>
			</template>
		</div>
	</section>
</template>

<style scoped>
.timeline { width: 100%; min-height: 0; overflow-y: auto; }
.timeline__content { display: flex; width: 100%; min-height: 100%; flex-direction: column; gap: 14px; padding: 24px clamp(16px, 4vw, 56px); }
.timeline__state { margin: auto; color: var(--color-text-maxcontrast); }
.timeline__day { display: flex; align-items: center; justify-content: center; margin: 8px 0; color: var(--color-text-maxcontrast); font-size: 12px; }
.timeline__day::before, .timeline__day::after { flex: 1; height: 1px; content: ''; background: var(--color-border); }
.timeline__day span { padding: 0 12px; }
.timeline__load-older { align-self: center; margin: 8px auto 0; padding: 6px 12px; border: none; border-radius: var(--border-radius-pill, 9999px); background: var(--color-background-dark); color: var(--color-text-maxcontrast); cursor: pointer; }
.timeline__load-older:disabled { opacity: 0.6; cursor: default; }
</style>
