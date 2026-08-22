import type { ComputedRef, Ref } from 'vue'

export interface MessageReferencePreviewContext {
	shouldRender: ComputedRef<boolean>
	expanded: Ref<boolean>
	isFullWidth: ComputedRef<boolean>
	toggleExpanded: () => void
	toggleWidth: () => void
}
