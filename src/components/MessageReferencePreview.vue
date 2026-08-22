<script setup lang="ts">
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { NcReferenceWidget } from '@nextcloud/vue/components/NcRichText'
import { translate as t } from '@nextcloud/l10n'
import { computed, nextTick, shallowRef, useTemplateRef, watch } from 'vue'
import { containsHttpLink, extractReferencePreview } from '../services/referenceApi'
import type { ReferencePreview } from '../types/reference'

type WidthMode = 'auto' | 'compact' | 'full'

const props = defineProps<{
	messageId: string
	text: string
	isOwn: boolean
}>()

const reference = shallowRef<ReferencePreview | null>(null)
const loading = shallowRef(false)
const expanded = shallowRef(true)
const widthMode = shallowRef<WidthMode>('auto')
const providerPrefersFullWidth = shallowRef(false)
const widgetRoot = useTemplateRef<HTMLElement>('widgetRoot')
let requestSequence = 0

interface ReferenceWidgetRegistration {
	fullWidth?: boolean
}

type ReferenceWidgetWindow = Window & typeof globalThis & {
	_vue_richtext_widgets?: Record<string, ReferenceWidgetRegistration | undefined>
}

function providerUsesFullWidth(richObjectType: string): boolean {
	return Boolean((window as ReferenceWidgetWindow)._vue_richtext_widgets?.[richObjectType]?.fullWidth)
}

const hasCandidateLink = computed(() => containsHttpLink(props.text))
const shouldRender = computed(() => hasCandidateLink.value && (loading.value || reference.value !== null))
const isFullWidth = computed(() => widthMode.value === 'full'
	|| (widthMode.value === 'auto' && providerPrefersFullWidth.value))

watch(
	() => [props.messageId, props.text] as const,
	async ([, text]) => {
		const sequence = ++requestSequence
		reference.value = null
		expanded.value = true
		widthMode.value = 'auto'
		providerPrefersFullWidth.value = false
		if (!containsHttpLink(text)) {
			loading.value = false
			return
		}

		loading.value = true
		try {
			const resolved = await extractReferencePreview(text)
			if (sequence === requestSequence) {
				reference.value = resolved
				providerPrefersFullWidth.value = resolved ? providerUsesFullWidth(resolved.richObjectType) : false
			}
		} catch {
			if (sequence === requestSequence) {
				reference.value = null
			}
		} finally {
			if (sequence === requestSequence) {
				loading.value = false
			}
		}
	},
	{ immediate: true },
)

watch(
	() => [reference.value, expanded.value] as const,
	async ([resolved, isExpanded]) => {
		if (!resolved || !isExpanded) return
		await nextTick()
		providerPrefersFullWidth.value = providerPrefersFullWidth.value
			|| widgetRoot.value?.querySelector('.widget-custom.full-width') !== null
	},
)

function toggleExpanded() {
	expanded.value = !expanded.value
}

function toggleWidth() {
	widthMode.value = isFullWidth.value ? 'compact' : 'full'
}

const previewControls = {
	shouldRender,
	expanded,
	isFullWidth,
	toggleExpanded,
	toggleWidth,
}
</script>

<template>
	<div
		class="reference-preview"
		:class="{ 'reference-preview--own': isOwn }">
		<slot :preview="previewControls" />
		<div
			v-if="shouldRender && expanded"
			class="reference-preview__area"
			:class="{ 'reference-preview__area--own': isOwn }"
			role="region"
			:aria-label="t('churchtools_chat', 'Link preview')">
			<div
				class="reference-preview__frame"
				:class="{ 'reference-preview__frame--full': isFullWidth }">
				<div v-if="loading" class="reference-preview__loading" role="status">
					<NcLoadingIcon :size="20" />
					<span>{{ t('churchtools_chat', 'Loading link preview…') }}</span>
				</div>
				<div v-else-if="reference" ref="widgetRoot" class="reference-preview__widget">
					<NcReferenceWidget :reference="reference" :interactive="true" />
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.reference-preview {
	--widget-full-width: 100%;
	display: flex;
	width: 100%;
	min-width: 0;
	flex-direction: column;
	gap: 4px;
}

.reference-preview__area {
	display: flex;
	width: 100%;
	min-width: 0;
	justify-content: flex-start;
	padding-inline-start: 42px;
}

.reference-preview__area--own {
	justify-content: flex-end;
	padding-inline: 0 42px;
}

.reference-preview__frame {
	width: min(560px, 100%);
	min-width: 0;
}

.reference-preview__frame--full {
	width: 100%;
}

.reference-preview__loading {
	display: flex;
	min-height: var(--default-clickable-area);
	align-items: center;
	gap: 8px;
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.reference-preview__widget {
	width: 100%;
	min-width: 0;
}

.reference-preview__widget :deep(.widget-custom) {
	max-width: 100%;
}

.reference-preview__widget :deep(.widget-custom.full-width) {
	inset-inline-start: 0;
}

@media (max-width: 700px) {
	.reference-preview__area,
	.reference-preview__area--own {
		padding-inline: 0;
	}

	.reference-preview__frame {
		width: 100%;
	}
}
</style>
