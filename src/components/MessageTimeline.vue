<script setup lang="ts">
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { translate as t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, watch, useTemplateRef } from 'vue'
import { useDayLabel } from '../composables/useDayLabel'
import type { ChatMessage } from '../types/chat'
import { lastReadOwnMessageId } from '../utils/readReceipts'
import { getReplyFallbackQuote, getReplyTargetId } from '../utils/relations'
import { buildTimeline, type TimelineItem } from '../utils/timeline'
import { typingLabel } from '../utils/typing'
import MessageBubble from './MessageBubble.vue'

const props = defineProps<{
	messages: readonly ChatMessage[]
	currentUserId: string
	loading: boolean
	hasMore: boolean
	focusMessageId: string | null
	replyTargets: Record<string, ChatMessage>
	typingUsers?: Array<{ id: string; displayName: string }>
	readReceipts?: Record<string, string>
}>()

const { dayLabel } = useDayLabel()

const decoratedMessages = computed<TimelineItem[]>(() => buildTimeline(props.messages))

const typingText = computed(() => typingLabel(props.typingUsers ?? []))
const lastReadMessageId = computed(() => lastReadOwnMessageId(props.messages, props.readReceipts, props.currentUserId))

const replyTargetMap = computed(() => {
	const map = new Map<string, ChatMessage>()
	for (const message of props.messages) {
		map.set(message.id, message)
	}
	for (const [eventId, message] of Object.entries(props.replyTargets)) {
		map.set(eventId, message)
	}
	return map
})

function replyContext(message: ChatMessage): { message: ChatMessage | null; canJump: boolean; fallback: string | null } {
	const targetId = getReplyTargetId(message)
	if (targetId === null) {
		return { message: null, canJump: false, fallback: getReplyFallbackQuote(message) }
	}
	return {
		message: replyTargetMap.value.get(targetId) ?? null,
		canJump: props.messages.some((item) => item.id === targetId),
		fallback: null,
	}
}

function jumpToReply(message: ChatMessage) {
	const targetId = getReplyTargetId(message)
	const target = targetId !== null ? replyTargetMap.value.get(targetId) : undefined
	if (target) emit('jump', target)
}

const emit = defineEmits<{
	retry: [message: ChatMessage]
	reply: [message: ChatMessage]
	react: [message: ChatMessage, emoji: string]
	unreact: [message: ChatMessage, emoji: string]
	delete: [message: ChatMessage]
	edit: [message: ChatMessage]
	jump: [message: ChatMessage]
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
		<div v-if="hasMore" class="timeline__load-older-row">
			<button type="button" class="timeline__load-older" :disabled="loading" @click="emit('loadOlder')">
				<NcLoadingIcon v-if="loading" :size="16" />
				<span>{{ t('churchtools_chat', 'Load older messages') }}</span>
			</button>
		</div>
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
						:reply-to-message="replyContext(message).message"
						:fallback-text="replyContext(message).fallback"
						:can-jump-reply="replyContext(message).canJump"
						:read-by-other="message.id === lastReadMessageId"
						@retry="emit('retry', $event)"
						@reply="emit('reply', $event)"
						@react="(message, emoji) => emit('react', message, emoji)"
						@unreact="(message, emoji) => emit('unreact', message, emoji)"
						@delete="emit('delete', $event)"
						@edit="emit('edit', $event)"
						@jump="jumpToReply(message)" />
				</template>
			</template>
			<div v-if="typingText && !loading" class="timeline__typing" role="status">
				<span class="timeline__typing-dots" aria-hidden="true"><i /><i /><i /></span>
				<span>{{ typingText }}</span>
			</div>
		</div>
	</section>
</template>

<style scoped>
.timeline { width: 100%; min-height: 0; flex: 1 1 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; }
.timeline__content { display: flex; width: 100%; min-height: 100%; flex-direction: column; gap: 14px; padding: 24px clamp(16px, 4vw, 56px); }
.timeline__state { margin: auto; color: var(--color-text-maxcontrast); }
.timeline__day {
	position: sticky;
	z-index: 2;
	top: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 4px 0;
	pointer-events: none;
}
.timeline__day span {
	padding: 4px 14px;
	border-radius: var(--border-radius-element, var(--border-radius-pill, 9999px));
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	white-space: nowrap;
	text-transform: capitalize;
}
.timeline__load-older-row { display: flex; width: 100%; flex: 0 0 auto; justify-content: center; padding-block-start: 8px; }
.timeline__load-older-row > .timeline__load-older { width: fit-content; margin: 0; padding: 6px 12px; border: none; border-radius: var(--border-radius-pill, 9999px); background: var(--color-background-dark); color: var(--color-text-maxcontrast); font-weight: normal; cursor: pointer; }
.timeline__load-older:disabled { opacity: 0.6; cursor: default; }
.timeline__typing {
	display: flex;
	align-items: center;
	gap: 8px;
	align-self: flex-start;
	padding: 6px 12px;
	border-radius: 4px 16px 16px 16px;
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}
.timeline__typing-dots { display: flex; gap: 3px; }
.timeline__typing-dots i {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: var(--color-text-maxcontrast);
	animation: typing-bounce 1.2s infinite ease-in-out;
}
.timeline__typing-dots i:nth-child(2) { animation-delay: 0.2s; }
.timeline__typing-dots i:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-bounce {
	0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
	30% { transform: translateY(-4px); opacity: 1; }
}
</style>
