<script setup lang="ts">
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { translate as t } from '@nextcloud/l10n'
import { computed, shallowRef } from 'vue'
import type { ChatAttachment } from '../types/chat'
import { attachmentDownloadUrl, attachmentThumbnailUrl, attachmentViewUrl, formatFileSize } from '../utils/attachments'

const props = withDefaults(defineProps<{ attachment: ChatAttachment; saving?: boolean }>(), { saving: false })
const emit = defineEmits<{ save: [attachment: ChatAttachment] }>()

const previewOpen = shallowRef(false)
const isImage = computed(() => props.attachment.kind === 'image')
// While an attachment is still uploading, mxcUrl is either a local blob:
// preview (images) or empty (other files), not a real mxc:// URI, so it
// can't be routed through the backend media endpoints yet.
const isPending = computed(() => !props.attachment.mxcUrl.startsWith('mxc://'))
const thumbnailUrl = computed(() => isPending.value ? props.attachment.mxcUrl : attachmentThumbnailUrl(props.attachment.mxcUrl))
const downloadUrl = computed(() => isPending.value ? undefined : attachmentDownloadUrl(props.attachment.mxcUrl, props.attachment.filename))
const viewUrl = computed(() => isPending.value ? props.attachment.mxcUrl : attachmentViewUrl(props.attachment.mxcUrl))
const sizeLabel = computed(() => formatFileSize(props.attachment.size))
const typeLabel = computed(() => props.attachment.mimeType || props.attachment.kind)
const fileIcon = computed(() => {
	const type = props.attachment.mimeType || ''
	if (type.startsWith('audio/')) return '♫'
	if (type.startsWith('video/')) return '▶'
	if (type.includes('pdf')) return 'PDF'
	if (type.includes('spreadsheet') || type.includes('excel')) return 'XLS'
	if (type.includes('word') || type.includes('document')) return 'DOC'
	if (type.includes('zip') || type.includes('archive') || type.includes('compressed')) return 'ZIP'
	return 'FILE'
})

function openPreview() {
	if (isImage.value) previewOpen.value = true
}

</script>

<template>
	<div class="attachment" :class="{ 'attachment--image': isImage }">
		<button v-if="isImage" class="attachment__image" type="button" :aria-label="t('churchtools_chat', 'Open image {filename}', { filename: attachment.filename })" @click="openPreview">
			<img :src="thumbnailUrl" :alt="attachment.filename">
		</button>
		<component :is="downloadUrl ? 'a' : 'div'" class="attachment__file" :href="downloadUrl" :download="downloadUrl ? attachment.filename : undefined">
			<span class="attachment__icon" aria-hidden="true">{{ fileIcon }}</span>
			<span class="attachment__details"><strong>{{ attachment.filename }}</strong><small>{{ typeLabel }}<template v-if="sizeLabel"> · {{ sizeLabel }}</template></small></span>
		</component>
		<NcDialog v-if="previewOpen" :name="attachment.filename" size="large" @closing="previewOpen = false">
			<div class="attachment__dialog"><img :src="viewUrl" :alt="attachment.filename"></div>
			<template #actions>
				<NcButton variant="secondary" :disabled="saving || isPending" @click="emit('save', attachment)">{{ t('churchtools_chat', 'Save to Nextcloud') }}</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<style scoped>
.attachment { position: relative; display: inline-flex; max-inline-size: 100%; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-main-background); overflow: hidden; }
.attachment--image { border: 0; border-radius: 0; background: transparent; overflow: visible; }
.attachment__image { display: block; padding: 0; border: 0; cursor: zoom-in; background: transparent; }
.attachment__image img { display: block; inline-size: 160px; block-size: 160px; object-fit: cover; }
.attachment__file { display: flex; min-inline-size: 220px; max-inline-size: 360px; align-items: center; gap: 10px; padding: 12px 42px 12px 12px; color: inherit; text-decoration: none; }
.attachment__file:hover { background: var(--color-background-hover); }
.attachment__icon { display: grid; inline-size: 36px; block-size: 42px; place-items: center; border-radius: 4px; color: var(--color-primary-element-text); background: var(--color-primary-element); font-size: 10px; font-weight: 700; }
.attachment__details { display: grid; min-inline-size: 0; gap: 3px; }
.attachment__details strong, .attachment__details small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.attachment__details small { color: var(--color-text-maxcontrast); }
.attachment__dialog { display: grid; max-block-size: min(75vh, 900px); place-items: center; }
.attachment__dialog img { display: block; max-inline-size: 100%; max-block-size: min(75vh, 900px); object-fit: contain; }
</style>
