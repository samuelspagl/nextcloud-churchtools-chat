<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, onBeforeUnmount, onMounted } from 'vue'
import type { ChatMessage, ChatRoom, RoomDetails } from '../types/chat'
import { displayableAvatarUrl } from '../utils/avatar'

const props = defineProps<{
	open: boolean
	room: ChatRoom
	details: RoomDetails | null
	loading: boolean
	error: string
	searchQuery: string
	searchResults: readonly ChatMessage[]
	searching: boolean
	searchError: string
}>()

const emit = defineEmits<{
	close: []
	'update:searchQuery': [query: string]
	focusMessage: [message: ChatMessage]
}>()

const resolved = computed(() => props.details ?? {
	roomId: props.room.id,
	name: props.room.name,
	avatarUrl: props.room.avatarUrl,
	kind: props.room.kind,
	memberCount: props.room.memberCount,
	topic: '',
	canonicalAlias: null,
	encrypted: props.room.encrypted,
	creator: null,
	joinRule: null,
	historyVisibility: null,
	members: [],
} satisfies RoomDetails)

const roomType = computed(() => resolved.value.kind === 'direct'
	? t('churchtools_chat', 'Direct conversation')
	: t('churchtools_chat', 'Group conversation'))

const accessDetails = computed(() => [
	{ label: t('churchtools_chat', 'Canonical alias'), value: resolved.value.canonicalAlias },
	{ label: t('churchtools_chat', 'Join rule'), value: resolved.value.joinRule },
	{ label: t('churchtools_chat', 'History visibility'), value: resolved.value.historyVisibility },
].filter((detail): detail is { label: string; value: string } => detail.value !== null && detail.value !== ''))

const hasTechnicalDetails = computed(() => resolved.value.creator !== null || resolved.value.roomId !== '' || accessDetails.value.length > 0)
const hasSearchTerm = computed(() => props.searchQuery.trim().length >= 2)

function updateSearchQuery(query: string | number) {
	emit('update:searchQuery', String(query))
}

function messageExcerpt(message: ChatMessage): string {
	return message.body || message.attachment?.filename || t('churchtools_chat', 'Attachment')
}

function formatMessageTime(timestamp: number): string {
	return new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(timestamp)
}

function closeOnEscape(event: KeyboardEvent) {
	if (props.open && event.key === 'Escape') {
		emit('close')
	}
}

onMounted(() => window.addEventListener('keydown', closeOnEscape))
onBeforeUnmount(() => window.removeEventListener('keydown', closeOnEscape))
</script>

<template>
	<NcAppSidebar
		:open="open"
		:name="resolved.name"
		:subname="resolved.kind === 'direct' ? t('churchtools_chat', 'Direct conversation') : t('churchtools_chat', 'Group conversation')"
		:title="resolved.name"
		:no-toggle="true"
		@update:open="!$event && emit('close')">
		<NcAppSidebarTab id="conversation-details" :name="t('churchtools_chat', 'Details')">
			<div class="details-hero">
				<div class="details-hero__avatar">
					<NcAvatar
						:url="displayableAvatarUrl(resolved.avatarUrl)"
						:display-name="resolved.name"
						:is-no-user="true"
						:disable-menu="true"
						:size="56" />
				</div>
				<div class="details-hero__identity">
					<h2>{{ resolved.name }}</h2>
				</div>
				<p v-if="resolved.topic" class="details-hero__topic">{{ resolved.topic }}</p>
			</div>

			<div class="details-search">
				<NcInputField
					:label="t('churchtools_chat', 'Search messages')"
					:model-value="searchQuery"
					:placeholder="t('churchtools_chat', 'Search messages in this conversation')"
					@update:model-value="updateSearchQuery" />
				<p v-if="searchQuery.trim().length > 0 && !hasSearchTerm" class="details-search__hint">{{ t('churchtools_chat', 'Enter at least two characters.') }}</p>
			</div>

			<section v-if="hasSearchTerm" class="details-section details-search-results" :aria-label="t('churchtools_chat', 'Message search results')">
				<h3>{{ t('churchtools_chat', 'Message search results') }}</h3>
				<div v-if="searching" class="details-search__state"><NcLoadingIcon :size="20" /><span>{{ t('churchtools_chat', 'Searching messages…') }}</span></div>
				<NcNoteCard v-else-if="searchError" type="error" :text="searchError" />
				<p v-else-if="searchResults.length === 0" class="details-empty">{{ t('churchtools_chat', 'No messages found.') }}</p>
				<ul v-else class="search-result-list">
					<li v-for="message in searchResults" :key="message.id">
						<button type="button" class="search-result" @click="emit('focusMessage', message)">
							<span class="search-result__meta"><strong>{{ message.senderName || message.sender }}</strong><time>{{ formatMessageTime(message.timestamp) }}</time></span>
							<span class="search-result__excerpt">{{ messageExcerpt(message) }}</span>
						</button>
					</li>
				</ul>
			</section>

			<div v-if="loading" class="details-state">
				<NcLoadingIcon :size="32" />
				<span>{{ t('churchtools_chat', 'Loading conversation details…') }}</span>
			</div>
			<NcNoteCard v-else-if="error" type="error" :text="error" />
			<template v-else>
				<NcNoteCard
					v-if="resolved.encrypted"
					type="warning"
					:text="t('churchtools_chat', 'This room is encrypted. Its messages cannot be displayed by the server-side gateway.')" />

				<section class="details-section" :aria-label="t('churchtools_chat', 'Room information')">
					<h3>{{ t('churchtools_chat', 'Room information') }}</h3>
					<dl class="details-rows">
						<div><dt>{{ t('churchtools_chat', 'Type') }}</dt><dd>{{ roomType }}</dd></div>
						<div><dt>{{ t('churchtools_chat', 'Participants') }}</dt><dd>{{ resolved.memberCount }}</dd></div>
					</dl>
				</section>

				<section class="details-section" :aria-label="t('churchtools_chat', 'Participants')">
					<div class="details-section__heading"><h3>{{ t('churchtools_chat', 'Participants') }}</h3><span>{{ resolved.memberCount }}</span></div>
					<p v-if="resolved.members.length === 0" class="details-empty">{{ t('churchtools_chat', 'No participant information is available.') }}</p>
					<ul v-else class="member-list">
						<li v-for="member in resolved.members" :key="member.id">
							<NcListItem :name="member.displayName" :title="member.id" one-line>
								<template #icon>
									<NcAvatar
										:url="displayableAvatarUrl(member.avatarUrl)"
										:display-name="member.displayName"
										:is-no-user="true"
										:disable-menu="true"
										:size="36" />
								</template>
								<template #subname>{{ member.id }}</template>
							</NcListItem>
						</li>
					</ul>
				</section>

				<details v-if="hasTechnicalDetails" class="technical-details">
					<summary>{{ t('churchtools_chat', 'Room technical details') }}</summary>
					<dl class="details-rows">
						<div v-if="resolved.creator"><dt>{{ t('churchtools_chat', 'Creator') }}</dt><dd>{{ resolved.creator }}</dd></div>
						<div><dt>{{ t('churchtools_chat', 'Room ID') }}</dt><dd>{{ resolved.roomId }}</dd></div>
						<div v-for="detail in accessDetails" :key="detail.label"><dt>{{ detail.label }}</dt><dd>{{ detail.value }}</dd></div>
					</dl>
				</details>
			</template>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<style scoped>
.details-hero { display: grid; justify-items: center; gap: 5px; padding: 12px 12px 10px; text-align: center; }
.details-hero__avatar { display: grid; padding: 2px; border: 1px solid var(--color-primary-element-light); border-radius: 50%; }
.details-hero__identity { display: grid; gap: 2px; }
.details-hero__identity h2 { margin: 0; font-size: 18px; }
.details-hero__topic { color: var(--color-text-maxcontrast); font-size: 13px; }
.details-hero__topic { max-inline-size: 100%; margin: 2px 0 0; white-space: pre-wrap; }
.details-search { padding: 0 12px 10px; }
.details-search__hint { margin: 5px 0 0; color: var(--color-text-maxcontrast); font-size: 12px; }
.details-search__state { display: flex; align-items: center; gap: 7px; padding: 8px 0; color: var(--color-text-maxcontrast); font-size: 13px; }
.details-state { display: flex; min-height: 140px; align-items: center; justify-content: center; gap: 8px; color: var(--color-text-maxcontrast); }
.details-section { padding: 11px 12px; border-top: 1px solid var(--color-border); }
.details-section h3 { margin: 0; font-size: 14px; }
.details-section__heading { display: flex; align-items: center; justify-content: space-between; margin-block-end: 5px; }
.details-section__heading span { min-inline-size: 24px; padding: 2px 7px; border-radius: var(--border-radius-pill); color: var(--color-primary-element-text); background: var(--color-primary-element); font-size: 12px; text-align: center; }
.details-rows { margin: 5px 0 0; }
.details-rows div { display: grid; grid-template-columns: minmax(100px, 42%) minmax(0, 1fr); align-items: baseline; gap: 10px; padding: 4px 0; }
.details-rows dt { color: var(--color-text-maxcontrast); font-size: 13px; }
.details-rows dt::after { content: ':'; }
.details-rows dd { overflow-wrap: anywhere; margin: 0; font-size: 13px; text-align: start; }
.details-empty { margin: 6px 0 0; color: var(--color-text-maxcontrast); font-size: 13px; }
.member-list { margin: 0 -4px; padding: 0; list-style: none; }
.technical-details { margin: 11px 12px; padding-top: 11px; border-top: 1px solid var(--color-border); }
.technical-details summary { color: var(--color-text-maxcontrast); cursor: pointer; font-size: 13px; }
.technical-details[open] summary { margin-block-end: 5px; }
.search-result-list { margin: 6px 0 0; padding: 0; list-style: none; }
.search-result { display: grid; width: 100%; gap: 3px; padding: 7px 0; border: 0; border-top: 1px solid var(--color-border); color: inherit; background: transparent; font-weight: normal; cursor: pointer; text-align: start; }
.search-result-list li:first-child .search-result { border-top: 0; }
.search-result:hover, .search-result:focus-visible { color: var(--color-primary-element); background: var(--color-background-hover); outline: none; }
.search-result__meta { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; }
.search-result__meta time { color: var(--color-text-maxcontrast); white-space: nowrap; }
.search-result__excerpt { overflow: hidden; color: var(--color-text-maxcontrast); font-size: 13px; font-weight: normal; text-overflow: ellipsis; white-space: nowrap; }
</style>
