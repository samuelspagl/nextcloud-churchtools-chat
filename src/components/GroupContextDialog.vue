<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed } from 'vue'
import type { GroupContextResponse, GroupLeader, TeamContext } from '../types/chat'
import { displayableAvatarUrl } from '../utils/avatar'

const props = defineProps<{
	context: GroupContextResponse
}>()

const emit = defineEmits<{
	close: []
	retry: []
}>()

const teamIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12,5.5A3.5,3.5 0 0,1 15.5,9A3.5,3.5 0 0,1 12,12.5A3.5,3.5 0 0,1 8.5,9A3.5,3.5 0 0,1 12,5.5M5,8C5.56,8 6.08,8.15 6.53,8.42C6.38,9.85 6.8,11.27 7.66,12.38C7.16,13.34 6.16,14 5,14A3,3 0 0,1 2,11A3,3 0 0,1 5,8M19,8A3,3 0 0,1 22,11A3,3 0 0,1 19,14C17.84,14 16.84,13.34 16.34,12.38C17.2,11.27 17.62,9.85 17.47,8.42C17.92,8.15 18.44,8 19,8M5.5,18.25C5.5,16.18 8.41,14.5 12,14.5C15.59,14.5 18.5,16.18 18.5,18.25V20H5.5V18.25M0,20V18.5C0,17.11 1.89,15.94 4.45,15.6C3.86,16.28 3.5,17.22 3.5,18.25V20H0M24,20H20.5V18.25C20.5,17.22 20.14,16.28 19.55,15.6C22.11,15.94 24,17.11 24,18.5V20Z" /></svg>'
const folderIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20,18H4V8H20M20,6H12L10,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V8C22,6.89 21.1,6 20,6Z" /></svg>'
const boardIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5A2,2 0 0,0 19,3M12,3A1,1 0 0,1 13,4A1,1 0 0,1 12,5A1,1 0 0,1 11,4A1,1 0 0,1 12,3M7,7H17V9H7V7M7,11H17V13H7V11M7,15H14V17H7V15Z" /></svg>'
const openIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z" /></svg>'
const visibilityIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z" /></svg>'
const groupTypeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M5.5,7A1.5,1.5 0 0,1 4,5.5A1.5,1.5 0 0,1 5.5,4A1.5,1.5 0 0,1 7,5.5A1.5,1.5 0 0,1 5.5,7M21.41,11.58L12.41,2.58C12.05,2.22 11.55,2 11,2H4C2.89,2 2,2.89 2,4V11C2,11.55 2.22,12.05 2.59,12.41L11.58,21.41C11.95,21.77 12.45,22 13,22C13.55,22 14.05,21.77 14.41,21.41L21.41,14.41C21.78,14.05 22,13.55 22,13C22,12.44 21.77,11.94 21.41,11.58Z" /></svg>'
const membersIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16,17V19H2V17S2,13 9,13 16,17M9,12A4,4 0 0,0 13,8A4,4 0 0,0 9,4A4,4 0 0,0 5,8A4,4 0 0,0 9,12M16.76,13.28C18.04,14.06 19,15.16 19,17V19H23V17C23,14.61 19.42,13.6 16.76,13.28M15,4A3.39,3.39 0 0,0 13.9,4.2A5,5 0 0,1 13.9,11.8A3.39,3.39 0 0,0 15,12A4,4 0 0,0 15,4Z" /></svg>'

const visibilityLabels: Record<string, string> = {
	public: t('churchtools_chat', 'Public'),
	internal: t('churchtools_chat', 'Internal'),
	restricted: t('churchtools_chat', 'Restricted'),
	hidden: t('churchtools_chat', 'Hidden'),
	unknown: t('churchtools_chat', 'Unknown'),
}

const group = computed(() => props.context.group)
const visibility = computed(() => group.value?.visibility ? visibilityLabels[group.value.visibility] : t('churchtools_chat', 'Not available'))

const allLeaders = computed<readonly GroupLeader[]>(() => {
	if (!group.value) return []
	const seen = new Set<number>()
	const leaders: GroupLeader[] = []
	for (const role of group.value.leadership) {
		for (const member of role.members) {
			if (seen.has(member.personId)) continue
			seen.add(member.personId)
			leaders.push(member)
		}
	}
	return leaders
})

function folders(team: TeamContext) {
	return team.resources.filter((resource) => resource.kind === 'folder')
}

function boards(team: TeamContext) {
	return team.resources.filter((resource) => resource.kind === 'deck-board')
}
</script>

<template>
	<NcDialog :name="t('churchtools_chat', 'Group information')" size="large" @closing="emit('close')">
		<div v-if="group" class="group-context">
			<section class="group-context__section" :aria-label="t('churchtools_chat', 'ChurchTools group')">
				<div class="group-context__heading">
					<h2 class="group-context__title">{{ group.name }}</h2>
					<NcButton
						v-if="group.frontendUrl"
						:href="group.frontendUrl"
						target="_blank"
						rel="noopener noreferrer">
						{{ t('churchtools_chat', 'Open in ChurchTools') }}
					</NcButton>
				</div>
				<div class="group-context__stats">
					<div class="group-context__stat" :title="t('churchtools_chat', 'Visibility')">
						<NcIconSvgWrapper :svg="visibilityIcon" :size="18" />
						<span>{{ visibility }}</span>
					</div>
					<div class="group-context__stat" :title="t('churchtools_chat', 'Group type')">
						<NcIconSvgWrapper :svg="groupTypeIcon" :size="18" />
						<span>{{ group.groupType || t('churchtools_chat', 'Not available') }}</span>
					</div>
					<div class="group-context__stat" :title="t('churchtools_chat', 'Category')">
						<NcIconSvgWrapper :svg="folderIcon" :size="18" />
						<span>{{ group.category || t('churchtools_chat', 'Not available') }}</span>
					</div>
					<div class="group-context__stat" :title="t('churchtools_chat', 'Members')">
						<NcIconSvgWrapper :svg="membersIcon" :size="18" />
						<span>{{ group.memberCount }}</span>
					</div>
				</div>
				<div v-if="group.description" class="group-context__description">
					<h3>{{ t('churchtools_chat', 'Description') }}</h3>
					<p>{{ group.description }}</p>
				</div>
			</section>

			<section class="group-context__section" :aria-label="t('churchtools_chat', 'Leadership')">
				<h3>{{ t('churchtools_chat', 'Leadership') }}</h3>
				<p v-if="allLeaders.length === 0" class="group-context__empty">{{ t('churchtools_chat', 'No visible leadership information is available.') }}</p>
				<div v-else class="group-context__leaders">
					<div v-for="leader in allLeaders" :key="leader.personId" class="group-context__leader">
						<NcAvatar :url="displayableAvatarUrl(leader.avatarUrl)" :display-name="leader.displayName" :is-no-user="true" :size="32" />
						<span>{{ leader.displayName }}</span>
					</div>
				</div>
			</section>

			<section class="group-context__section" :aria-label="t('churchtools_chat', 'Nextcloud teams')">
				<h3>{{ t('churchtools_chat', 'Nextcloud teams') }}</h3>
				<NcNoteCard
					v-if="context.nextcloud.status === 'error'"
					type="error"
					:text="t('churchtools_chat', 'Nextcloud team resources could not be loaded.')">
					<NcButton @click="emit('retry')">{{ t('churchtools_chat', 'Retry') }}</NcButton>
				</NcNoteCard>
				<p v-else-if="context.nextcloud.status === 'unavailable'" class="group-context__empty">{{ t('churchtools_chat', 'Nextcloud Teams is not available.') }}</p>
				<p v-else-if="context.nextcloud.teams.length === 0" class="group-context__empty">{{ t('churchtools_chat', 'No matching Nextcloud team was found.') }}</p>
				<template v-else>
					<div v-for="team in context.nextcloud.teams" :key="team.id" class="group-context__nc-group">
						<div class="group-context__nc-row">
							<div class="group-context__nc-label">
								<NcIconSvgWrapper :svg="teamIcon" :size="18" />
								<span>{{ t('churchtools_chat', 'Team') }}</span>
							</div>
							<a v-if="team.url" :href="team.url" target="_blank" rel="noopener noreferrer" class="group-context__nc-item">
								<span>{{ team.name }}</span>
								<NcIconSvgWrapper :svg="openIcon" :size="16" class="group-context__nc-open" />
							</a>
							<span v-else class="group-context__nc-item">{{ team.name }}</span>
						</div>
						<div v-if="folders(team).length > 0" class="group-context__nc-row">
							<div class="group-context__nc-label">
								<NcIconSvgWrapper :svg="folderIcon" :size="18" />
								<span>{{ t('churchtools_chat', 'Folders ({count})', { count: folders(team).length }) }}</span>
							</div>
							<ul class="group-context__nc-list">
								<li v-for="resource in folders(team)" :key="resource.id">
									<a :href="resource.url" target="_blank" rel="noopener noreferrer" class="group-context__nc-item">
										<span>{{ resource.label }}</span>
										<NcIconSvgWrapper :svg="openIcon" :size="16" class="group-context__nc-open" />
									</a>
								</li>
							</ul>
						</div>
						<div v-if="boards(team).length > 0" class="group-context__nc-row">
							<div class="group-context__nc-label">
								<NcIconSvgWrapper :svg="boardIcon" :size="18" />
								<span>{{ t('churchtools_chat', 'Deck boards ({count})', { count: boards(team).length }) }}</span>
							</div>
							<ul class="group-context__nc-list">
								<li v-for="resource in boards(team)" :key="resource.id">
									<a :href="resource.url" target="_blank" rel="noopener noreferrer" class="group-context__nc-item">
										<span>{{ resource.label }}</span>
										<NcIconSvgWrapper :svg="openIcon" :size="16" class="group-context__nc-open" />
									</a>
								</li>
							</ul>
						</div>
					</div>
				</template>
			</section>
		</div>
	</NcDialog>
</template>

<style scoped>
.group-context { display: grid; gap: 18px; padding: 4px 8px 16px; }
.group-context__section { display: grid; gap: 10px; }
.group-context__section + .group-context__section { padding-block-start: 16px; border-block-start: 1px solid var(--color-border); }
.group-context__heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.group-context__title, .group-context__section h3 { margin: 0; }
.group-context__stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.group-context__stat { display: flex; align-items: center; gap: 6px; padding: 6px 8px; border: 1px solid var(--color-border); border-radius: var(--border-radius); overflow: hidden; color: var(--color-text-maxcontrast); }
.group-context__stat svg { flex-shrink: 0; }
.group-context__stat span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--color-main-text); }
.group-context__description p { margin: 4px 0 0; white-space: pre-wrap; }
.group-context__leaders { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.group-context__leader { display: flex; align-items: center; gap: 8px; overflow-wrap: anywhere; }
.group-context__empty { margin: 0; color: var(--color-text-maxcontrast); }

.group-context__nc-group { display: grid; }
.group-context__nc-group + .group-context__nc-group { padding-block-start: 12px; border-block-start: 1px solid var(--color-border); }
.group-context__nc-row { display: grid; grid-template-columns: minmax(140px, 35%) minmax(0, 1fr); gap: 12px; align-items: start; padding-block: 8px; }
.group-context__nc-row + .group-context__nc-row { border-block-start: 1px solid var(--color-border); }
.group-context__nc-label { display: flex; align-items: center; gap: 8px; color: var(--color-text-maxcontrast); }
.group-context__nc-list { margin: 0; padding: 0; list-style: none; display: grid; gap: 4px; }
.group-context__nc-item, a.group-context__nc-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--color-main-text); text-decoration: none; overflow-wrap: anywhere; }
a.group-context__nc-item:hover span { text-decoration: underline; }
.group-context__nc-open { flex-shrink: 0; color: var(--color-main-text); }
</style>
