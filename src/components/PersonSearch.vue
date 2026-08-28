<script setup lang="ts">
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { translate as t } from '@nextcloud/l10n'
import { computed, watch } from 'vue'
import type { PersonSearchResult } from '../types/chat'
import { displayableAvatarUrl } from '../utils/avatar'

const props = defineProps<{
	results: readonly PersonSearchResult[]
	loading: boolean
	startingPersonId: number | null
	error: string
}>()

const query = defineModel<string>('query', { required: true })
const emit = defineEmits<{
	close: []
	search: [query: string]
	select: [person: PersonSearchResult]
}>()

const trimmedQuery = computed(() => query.value.trim())

watch(query, (value, _previous, onCleanup) => {
	const timer = window.setTimeout(() => emit('search', value.trim()), 350)
	onCleanup(() => window.clearTimeout(timer))
}, { immediate: true })
</script>

<template>
	<aside class="person-search" :aria-label="t('churchtools_chat', 'Start a new chat')">
		<header class="person-search__heading">
			<div class="person-search__title">
				<h1>{{ t('churchtools_chat', 'New chat') }}</h1>
				<NcButton variant="tertiary" @click="emit('close')">
					{{ t('churchtools_chat', 'Close') }}
				</NcButton>
			</div>
			<NcInputField
				v-model="query"
				:label="t('churchtools_chat', 'Search people by name')"
				:placeholder="t('churchtools_chat', 'For example, Anna Schmidt')" />
		</header>

		<p v-if="trimmedQuery.length < 2" class="person-search__state">
			{{ t('churchtools_chat', 'Enter at least two characters.') }}
		</p>
		<div v-else-if="loading" class="person-search__state">
			<NcLoadingIcon :size="32" />
			<span>{{ t('churchtools_chat', 'Searching ChurchTools…') }}</span>
		</div>
		<p v-else-if="error" class="person-search__state person-search__state--error" role="alert">
			{{ error }}
		</p>
		<p v-else-if="props.results.length === 0" class="person-search__state">
			{{ t('churchtools_chat', 'No people found.') }}
		</p>
		<ul v-else class="person-search__results">
			<li v-for="person in props.results" :key="person.id">
				<button
					type="button"
					class="person-result"
					:disabled="startingPersonId !== null"
					@click="emit('select', person)">
					<NcAvatar
						:display-name="person.displayName"
						:size="40"
						:url="displayableAvatarUrl(person.imageUrl)" />
					<span class="person-result__body">
						<strong>{{ person.displayName }}</strong>
						<span v-if="person.info">{{ person.info }}</span>
					</span>
					<NcLoadingIcon v-if="startingPersonId === person.id" :size="24" />
				</button>
			</li>
		</ul>
	</aside>
</template>

<style scoped>
.person-search { display: flex; min-height: 0; block-size: 100%; flex-direction: column; overflow: hidden; background: var(--color-main-background); }
.person-search__heading { display: grid; flex: 0 0 auto; gap: 12px; padding: 16px; border-bottom: 1px solid var(--color-border); }
.person-search__title { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.person-search__title h1 { margin: 0; font-size: 20px; }
.person-search__state { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 120px; padding: 16px; color: var(--color-text-maxcontrast); text-align: center; }
.person-search__state--error { color: var(--color-error-text); }
.person-search__results { min-height: 0; flex: 1 1 0; overflow-y: auto; overscroll-behavior: contain; margin: 0; padding: 8px; list-style: none; }
.person-result { display: flex; width: 100%; min-height: 60px; align-items: center; gap: 10px; padding: 8px; border: 0; border-radius: var(--border-radius-large); color: var(--color-main-text); background: transparent; text-align: start; cursor: pointer; }
.person-result:hover, .person-result:focus-visible { background: var(--color-background-hover); }
.person-result:focus-visible { outline: 2px solid var(--color-primary-element); outline-offset: -2px; }
.person-result:disabled { cursor: wait; opacity: .7; }
.person-result__body { display: grid; min-width: 0; flex: 1; }
.person-result__body strong, .person-result__body span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.person-result__body span { color: var(--color-text-maxcontrast); font-size: 13px; }
</style>
