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

		const actionButtons = wrapper.findAll('div > div button')
		const smartPickerButton = actionButtons.find((button) => button.text().includes('Smart Picker'))
		await smartPickerButton?.trigger('click')
		expect(wrapper.emitted('openSmartPicker')).toHaveLength(1)
	})

	it('opens the file picker and emits selected files', async () => {
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

		await wrapper.get('button[aria-label="Add content"]').trigger('click')
		const actionButtons = wrapper.findAll('div > div button')
		const attachButton = actionButtons.find((button) => button.text().includes('Attach file'))
		expect(attachButton).toBeDefined()

		const input = wrapper.get('input[type="file"]')
		const file = new File(['content'], 'photo.png', { type: 'image/png' })
		Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
		await input.trigger('change')

		const emitted = wrapper.emitted('filesSelected')
		expect(emitted).toHaveLength(1)
		expect((emitted?.[0]?.[0] as FileList)[0]).toBe(file)
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
