<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { translate as t } from '@nextcloud/l10n'
import { onBeforeUnmount, shallowRef, watch } from 'vue'
import { formatFileSize } from '../utils/attachments'

const props = defineProps<{ files: File[] }>()
const emit = defineEmits<{ remove: [file: File] }>()

const removeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 10.6 6.7 5.3 5.3 6.7l5.3 5.3-5.3 5.3 1.4 1.4 5.3-5.3 5.3 5.3 1.4-1.4-5.3-5.3 5.3-5.3-1.4-1.4Z"/></svg>'

const previewUrls = shallowRef(new Map<File, string>())

watch(() => props.files, (files) => {
	const next = new Map<File, string>()
	for (const file of files) {
		if (!file.type.startsWith('image/')) continue
		next.set(file, previewUrls.value.get(file) ?? URL.createObjectURL(file))
	}
	for (const [file, url] of previewUrls.value) {
		if (!next.has(file)) URL.revokeObjectURL(url)
	}
	previewUrls.value = next
}, { immediate: true })

onBeforeUnmount(() => {
	for (const url of previewUrls.value.values()) URL.revokeObjectURL(url)
})

function isImage(file: File): boolean {
	return file.type.startsWith('image/')
}

function fileIcon(file: File): string {
	const type = file.type
	if (type.startsWith('audio/')) return '♫'
	if (type.startsWith('video/')) return '▶'
	if (type.includes('pdf')) return 'PDF'
	if (type.includes('spreadsheet') || type.includes('excel')) return 'XLS'
	if (type.includes('word') || type.includes('document')) return 'DOC'
	if (type.includes('zip') || type.includes('archive') || type.includes('compressed')) return 'ZIP'
	return 'FILE'
}
</script>

<template>
	<div class="pending-attachments">
		<div v-for="file in files" :key="`${file.name}-${file.size}-${file.lastModified}`" class="pending-attachment">
			<img v-if="isImage(file)" class="pending-attachment__thumb" :src="previewUrls.get(file)" :alt="file.name">
			<span v-else class="pending-attachment__icon" aria-hidden="true">{{ fileIcon(file) }}</span>
			<span class="pending-attachment__details">
				<strong>{{ file.name }}</strong>
				<small>{{ formatFileSize(file.size) }}</small>
			</span>
			<NcButton
				class="pending-attachment__remove"
				variant="tertiary"
				:aria-label="t('churchtools_chat', 'Remove attachment')"
				:title="t('churchtools_chat', 'Remove attachment')"
				@click="emit('remove', file)">
				<template #icon>
					<NcIconSvgWrapper :svg="removeIcon" :size="16" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<style scoped>
.pending-attachments { display: flex; width: 100%; min-width: 0; gap: 8px; margin-block-end: 6px; overflow-x: auto; }
.pending-attachment { position: relative; display: flex; flex: 0 0 auto; align-items: center; gap: 8px; max-inline-size: 220px; padding: 6px 34px 6px 6px; border-radius: var(--border-radius-large); background: var(--color-background-hover); }
.pending-attachment__thumb { inline-size: 36px; block-size: 36px; border-radius: 4px; object-fit: cover; flex: 0 0 auto; }
.pending-attachment__icon { display: grid; inline-size: 36px; block-size: 36px; flex: 0 0 auto; place-items: center; border-radius: 4px; color: var(--color-primary-element-text); background: var(--color-primary-element); font-size: 10px; font-weight: 700; }
.pending-attachment__details { display: grid; min-inline-size: 0; gap: 2px; }
.pending-attachment__details strong, .pending-attachment__details small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pending-attachment__details small { color: var(--color-text-maxcontrast); }
.pending-attachment__remove { position: absolute; inset-block-start: 2px; inset-inline-end: 2px; }
</style>
