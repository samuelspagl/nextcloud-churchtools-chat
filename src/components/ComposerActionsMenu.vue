<script setup lang="ts">
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { translate as t } from '@nextcloud/l10n'

defineProps<{ disabled: boolean }>()
const emit = defineEmits<{ openSmartPicker: [] }>()

const plusIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2Z"/></svg>'
const smartPickerIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7.5 5.6 1.1-2.5 1.1 2.5 2.5 1.1-2.5 1.1-1.1 2.5-1.1-2.5-2.5-1.1 2.5-1.1Zm8.3 6.6 1.6-3.6 1.6 3.6 3.6 1.6-3.6 1.6-1.6 3.6-1.6-3.6-3.6-1.6 3.6-1.6ZM5.6 15.5l1 2.2 2.2 1-2.2 1-1 2.2-1-2.2-2.2-1 2.2-1 1-2.2Z"/></svg>'
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
		<NcActionButton close-after-click @click="emit('openSmartPicker')">
			<template #icon>
				<NcIconSvgWrapper :svg="smartPickerIcon" :size="20" />
			</template>
			{{ t('churchtools_chat', 'Smart Picker') }}
		</NcActionButton>
	</NcActions>
</template>
