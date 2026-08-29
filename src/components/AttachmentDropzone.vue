<script setup lang="ts">
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { translate as t } from '@nextcloud/l10n'
import { shallowRef } from 'vue'

const props = defineProps<{ disabled: boolean }>()
const emit = defineEmits<{ filesDropped: [files: FileList] }>()

const uploadIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96ZM14 13v4h-4v-4H7l5-5 5 5h-3Z"/></svg>'

const active = shallowRef(false)
let dragDepth = 0

function hasFiles(event: DragEvent): boolean {
	return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

function onDragEnter(event: DragEvent) {
	if (props.disabled || !hasFiles(event)) return
	event.preventDefault()
	dragDepth++
	active.value = true
}

function onDragOver(event: DragEvent) {
	if (props.disabled || !hasFiles(event)) return
	event.preventDefault()
}

function onDragLeave(event: DragEvent) {
	if (!hasFiles(event)) return
	dragDepth = Math.max(0, dragDepth - 1)
	if (dragDepth === 0) active.value = false
}

function onDrop(event: DragEvent) {
	if (props.disabled || !hasFiles(event)) return
	event.preventDefault()
	dragDepth = 0
	active.value = false
	const files = event.dataTransfer?.files
	if (files && files.length > 0) emit('filesDropped', files)
}
</script>

<template>
	<div class="dropzone" @dragenter="onDragEnter" @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop">
		<slot />
		<div v-if="active" class="dropzone__overlay" :aria-label="t('churchtools_chat', 'Drop files here to add them to your message')" role="status">
			<div class="dropzone__badge">
				<NcIconSvgWrapper :svg="uploadIcon" :size="40" />
			</div>
		</div>
	</div>
</template>

<style scoped>
.dropzone { position: relative; display: flex; min-width: 0; min-height: 0; flex: 1; }
.dropzone > :first-child { min-width: 0; min-height: 0; flex: 1; }
.dropzone__overlay {
	position: absolute;
	inset: 0;
	z-index: 10;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 8px;
	border: 3px dashed var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background: color-mix(in srgb, var(--color-main-background) 85%, transparent);
	pointer-events: none;
}
.dropzone__badge {
	display: flex;
	inline-size: 72px;
	block-size: 72px;
	align-items: center;
	justify-content: center;
	border-radius: 50%;
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
}
</style>
