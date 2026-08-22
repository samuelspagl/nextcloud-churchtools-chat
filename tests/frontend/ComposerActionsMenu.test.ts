// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import ComposerActionsMenu from '../../src/components/ComposerActionsMenu.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

const NcActionsStub = defineComponent({
	props: {
		ariaLabel: { type: String, default: '' },
		disabled: { type: Boolean, default: false },
	},
	data: () => ({ open: false }),
	template: `
		<div>
			<button type="button" :aria-label="ariaLabel" :disabled="disabled" @click="open = !open">
				<slot name="icon" />
			</button>
			<div v-if="open"><slot /></div>
		</div>
	`,
})

const NcActionButtonStub = defineComponent({
	emits: ['click'],
	template: '<button type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
})

describe('ComposerActionsMenu', () => {
	it('opens the Talk-like plus menu and emits the Smart Picker action', async () => {
		const wrapper = mount(ComposerActionsMenu, {
			props: { disabled: false },
			global: {
				stubs: {
					NcActions: NcActionsStub,
					NcActionButton: NcActionButtonStub,
					NcIconSvgWrapper: true,
				},
			},
		})

		expect(wrapper.text()).not.toContain('Smart Picker')
		await wrapper.get('button[aria-label="Add content"]').trigger('click')
		expect(wrapper.text()).toContain('Smart Picker')

		await wrapper.get('div > div button').trigger('click')
		expect(wrapper.emitted('openSmartPicker')).toHaveLength(1)
	})

	it('disables the plus menu together with the composer', () => {
		const wrapper = mount(ComposerActionsMenu, {
			props: { disabled: true },
			global: {
				stubs: {
					NcActions: NcActionsStub,
					NcActionButton: NcActionButtonStub,
					NcIconSvgWrapper: true,
				},
			},
		})

		expect(wrapper.get('button[aria-label="Add content"]').attributes('disabled')).toBeDefined()
	})
})
