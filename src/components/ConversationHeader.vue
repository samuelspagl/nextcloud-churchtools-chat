<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import { computed } from 'vue'
import type { ChatRoom } from '../types/chat'
import { displayableAvatarUrl } from '../utils/avatar'

const props = defineProps<{
	room: ChatRoom
	detailsOpen: boolean
}>()

const emit = defineEmits<{
	back: []
	toggleDetails: []
}>()

const subtitle = computed(() => {
	if (props.room.encrypted) return t('churchtools_chat', 'Encrypted Matrix rooms are not supported')
	if (props.room.kind === 'direct') return t('churchtools_chat', 'Direct conversation')
	return t('churchtools_chat', '{count} participants', { count: props.room.memberCount })
})
</script>

<template>
	<header class="conversation-header">
		<NcButton
			class="conversation-header__back"
			variant="tertiary"
			:aria-label="t('churchtools_chat', 'Back to conversations')"
			@click="emit('back')">
			<template #icon>
				<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.42-1.41L7.83 13H20v-2Z" /></svg>
			</template>
		</NcButton>
		<NcAvatar
			:url="displayableAvatarUrl(room.avatarUrl)"
			:display-name="room.name"
			:is-no-user="true"
			:disable-menu="true"
			:size="40" />
		<div class="conversation-header__identity">
			<h2 :title="room.name">{{ room.name }}</h2>
			<p>{{ subtitle }}</p>
		</div>
		<NcButton
			class="conversation-header__details"
			variant="tertiary"
			:aria-label="detailsOpen ? t('churchtools_chat', 'Close conversation details') : t('churchtools_chat', 'Open conversation details')"
			:aria-pressed="detailsOpen"
			@click="emit('toggleDetails')">
			<template #icon>
				<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M11 17h2v-6h-2v6Zm1-15a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm-1-11h2V7h-2v2Z" /></svg>
			</template>
		</NcButton>
	</header>
</template>

<style scoped>
.conversation-header { display: flex; min-height: 68px; flex: 0 0 auto; align-items: center; gap: 12px; padding: 8px 12px 8px 20px; border-bottom: 1px solid var(--color-border); background: var(--color-main-background); }
.conversation-header__identity { min-width: 0; flex: 1; }
.conversation-header__identity h2, .conversation-header__identity p { overflow: hidden; margin: 0; white-space: nowrap; text-overflow: ellipsis; }
.conversation-header__identity h2 { font-size: 18px; line-height: 24px; }
.conversation-header__identity p { color: var(--color-text-maxcontrast); font-size: 13px; }
.conversation-header__back { display: none !important; }
.conversation-header__details[aria-pressed="true"] { color: var(--color-primary-element); background: var(--color-primary-element-light); }
.conversation-header svg { width: 24px; height: 24px; fill: currentColor; }
@media (max-width: 639px) {
	.conversation-header { padding-inline-start: 4px; }
	.conversation-header__back { display: inline-flex !important; }
}
</style>
