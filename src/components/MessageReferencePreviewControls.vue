<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { translate as t } from '@nextcloud/l10n'
import { computed } from 'vue'
import type { MessageReferencePreviewContext } from './messageReferencePreviewContext'

const props = defineProps<{
	preview: MessageReferencePreviewContext
}>()

const widthButtonLabel = computed(() => props.preview.isFullWidth.value
	? t('churchtools_chat', 'Use compact preview width')
	: t('churchtools_chat', 'Use full preview width'))
const visibilityButtonLabel = computed(() => props.preview.expanded.value
	? t('churchtools_chat', 'Hide link preview')
	: t('churchtools_chat', 'Show link preview'))

const showPreviewIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5C21.27 7.61 17 4.5 12 4.5Zm0 12.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>'
const hidePreviewIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m2.1 3.51 1.41-1.42 18.4 18.4-1.42 1.42-3.07-3.07A11.8 11.8 0 0 1 12 20C7 20 2.73 16.89 1 12a12.9 12.9 0 0 1 3.23-4.68L2.1 3.51ZM12 6.5c5 0 9.27 3.11 11 7.5a12.5 12.5 0 0 1-2.06 3.35l-3.08-3.08A6 6 0 0 0 9.73 6.14L7.88 4.29A12.1 12.1 0 0 1 12 3.5v3Zm0 3a2.5 2.5 0 0 1 2.5 2.5c0 .29-.05.57-.14.83l-3.19-3.19c.26-.09.54-.14.83-.14Zm-2.36 1.67 3.19 3.19A2.5 2.5 0 0 1 9.64 11.17Z"/></svg>'
const fullWidthIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 1.41 1.41L5.83 11H18.17l-2.58-2.59L17 7l5 5-5 5-1.41-1.41L18.17 13H5.83l2.58 2.59L7 17l-5-5 5-5Z"/></svg>'
const compactWidthIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 7 1.41 1.41L7.83 11H16.17l-2.58-2.59L15 7l5 5-5 5-1.41-1.41L16.17 13H7.83l2.58 2.59L9 17l-5-5 5-5Z"/></svg>'
</script>

<template>
	<div v-if="preview.shouldRender.value" class="reference-preview-controls">
		<NcButton
			v-if="preview.expanded.value"
			variant="tertiary"
			:aria-label="widthButtonLabel"
			:title="widthButtonLabel"
			:aria-pressed="preview.isFullWidth.value"
			@click="preview.toggleWidth">
			<template #icon>
				<NcIconSvgWrapper :svg="preview.isFullWidth.value ? compactWidthIcon : fullWidthIcon" />
			</template>
		</NcButton>
		<NcButton
			variant="tertiary"
			:aria-label="visibilityButtonLabel"
			:title="visibilityButtonLabel"
			:aria-expanded="preview.expanded.value"
			@click="preview.toggleExpanded">
			<template #icon>
				<NcIconSvgWrapper :svg="preview.expanded.value ? hidePreviewIcon : showPreviewIcon" />
			</template>
		</NcButton>
	</div>
</template>

<style scoped>
.reference-preview-controls {
	display: flex;
	flex: 0 0 auto;
	align-items: center;
	gap: 2px;
}
</style>
