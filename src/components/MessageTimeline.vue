<script setup lang="ts">
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { translate as t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, watch, useTemplateRef } from 'vue'
import { useDayLabel } from '../composables/useDayLabel'
import type { ChatMessage } from '../types/chat'
import { getReplyFallbackQuote, getReplyTargetId } from '../utils/relations'
import { buildTimeline, type TimelineItem } from '../utils/timeline'
import MessageBubble from './MessageBubble.vue'

const props = defineProps<{
	messages: readonly ChatMessage[]
	currentUserId: string
	loading: boolean
	hasMore: boolean
	focusMessageId: string | null
	replyTargets: Record<string, ChatMessage>
}>()

const { dayLabel } = useDayLabel()

const decoratedMessages = computed<TimelineItem[]>(() => buildTimeline(props.messages))

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
	delete: [message: ChatMessage]
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
						:reply-to-message="replyContext(message).message"
						:fallback-text="replyContext(message).fallback"
						:can-jump-reply="replyContext(message).canJump"
						@retry="emit('retry', $event)"
						@reply="emit('reply', $event)"
						@react="(message, emoji) => emit('react', message, emoji)"
						@delete="emit('delete', $event)"
						@jump="jumpToReply(message)" />
				</template>
			</template>
		</div>
	</section>
</template>

<style scoped>
.timeline { width: 100%; min-height: 0; overflow-y: auto; }
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
.timeline__load-older { align-self: center; margin: 8px auto 0; padding: 6px 12px; border: none; border-radius: var(--border-radius-pill, 9999px); background: var(--color-background-dark); color: var(--color-text-maxcontrast); cursor: pointer; }
.timeline__load-older:disabled { opacity: 0.6; cursor: default; }
</style>
