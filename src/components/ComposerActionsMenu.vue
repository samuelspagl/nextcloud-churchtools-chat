<script setup lang="ts">
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { translate as t } from '@nextcloud/l10n'
import { useTemplateRef } from 'vue'

defineProps<{ disabled: boolean }>()
const emit = defineEmits<{ openSmartPicker: []; filesSelected: [files: FileList] }>()

const fileInput = useTemplateRef<HTMLInputElement>('fileInput')

const plusIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2Z"/></svg>'
const smartPickerIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7.5 5.6 1.1-2.5 1.1 2.5 2.5 1.1-2.5 1.1-1.1 2.5-1.1-2.5-2.5-1.1 2.5-1.1Zm8.3 6.6 1.6-3.6 1.6 3.6 3.6 1.6-3.6 1.6-1.6 3.6-1.6-3.6-3.6-1.6 3.6-1.6ZM5.6 15.5l1 2.2 2.2 1-2.2 1-1 2.2-1-2.2-2.2-1 2.2-1 1-2.2Z"/></svg>'
const attachIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 6.5v10a4 4 0 0 1-8 0V6a2.5 2.5 0 0 1 5 0v9a1 1 0 0 1-2 0V7h-1.5v8a2.5 2.5 0 0 0 5 0V6a4 4 0 1 0-8 0v10.5a5.5 5.5 0 0 0 11 0V6.5h-1.5Z"/></svg>'

function openFilePicker() {
	fileInput.value?.click()
}

function onFilesChosen(event: Event) {
	const input = event.target as HTMLInputElement
	if (input.files && input.files.length > 0) emit('filesSelected', input.files)
	input.value = ''
}
</script>

<template>
	<NcActions
		:aria-label="t('churchtools_chat', 'Add content')"
		:disabled="disabled"
		:force-menu="true"
		placement="top-start">
		<template #icon>
			<NcIconSvgWrapper :svg="plusIcon" :size="24" />
		</template>
		<NcActionButton close-after-click @click="openFilePicker">
			<template #icon>
				<NcIconSvgWrapper :svg="attachIcon" :size="20" />
			</template>
			{{ t('churchtools_chat', 'Attach file') }}
		</NcActionButton>
		<NcActionButton close-after-click @click="emit('openSmartPicker')">
			<template #icon>
				<NcIconSvgWrapper :svg="smartPickerIcon" :size="20" />
			</template>
			{{ t('churchtools_chat', 'Smart Picker') }}
		</NcActionButton>
	</NcActions>
	<input ref="fileInput" type="file" multiple hidden @change="onFilesChosen">
</template>
