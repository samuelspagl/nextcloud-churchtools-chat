// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MessageComposer from '../../src/components/MessageComposer.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

const showTribute = vi.fn()

const NcRichContenteditableStub = defineComponent({
	props: {
		modelValue: { type: String, default: '' },
		disabled: { type: Boolean, default: false },
		linkAutocomplete: { type: Boolean, default: false },
		emojiAutocomplete: { type: Boolean, default: true },
		placeholder: { type: String, default: '' },
	},
	emits: ['update:modelValue', 'submit'],
	setup(_props, { emit, expose }) {
		expose({ showTribute })
		function update(event: Event) {
			emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
		}
		return { update }
	},
	template: '<textarea :value="modelValue" :disabled="disabled" :placeholder="placeholder" @input="update" />',
})

const NcButtonStub = defineComponent({
	props: {
		disabled: { type: Boolean, default: false },
		type: { type: String, default: 'button' },
	},
	emits: ['click'],
	template: '<button :type="type" :disabled="disabled" @click="$emit(\'click\', $event)"><slot name="icon" /><slot /></button>',
})

const NcActionsStub = defineComponent({
	props: {
		ariaLabel: { type: String, default: '' },
		disabled: { type: Boolean, default: false },
	},
	data: () => ({ open: false }),
	template: `
		<div>
			<button type="button" :aria-label="ariaLabel" :disabled="disabled" @click="open = true"><slot name="icon" /></button>
			<div v-if="open"><slot /></div>
		</div>
	`,
})

const NcActionButtonStub = defineComponent({
	emits: ['click'],
	template: '<button type="button" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
})

function mountComposer(disabled = false) {
	return mount(MessageComposer, {
		props: { disabled },
		global: {
			stubs: {
				NcActionButton: NcActionButtonStub,
				NcActions: NcActionsStub,
				NcButton: NcButtonStub,
				NcIconSvgWrapper: true,
				NcRichContenteditable: NcRichContenteditableStub,
			},
		},
	})
}

describe('MessageComposer', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('opens the dynamic Smart Picker from the plus menu', async () => {
		const wrapper = mountComposer()

		await wrapper.get('button[aria-label="Add content"]').trigger('click')
		const smartPickerButton = wrapper.findAll('button').find((button) => button.text().includes('Smart Picker'))
		expect(smartPickerButton).toBeDefined()
		await smartPickerButton!.trigger('click')

		expect(showTribute).toHaveBeenCalledWith('/')
	})

	it('keeps slash autocomplete enabled and emoji autocomplete disabled', () => {
		const wrapper = mountComposer()
		const editor = wrapper.getComponent(NcRichContenteditableStub)

		expect(editor.props('linkAutocomplete')).toBe(true)
		expect(editor.props('emojiAutocomplete')).toBe(false)
		expect(editor.props('placeholder')).toBe('Write a message…')
	})

	it('enables the icon send button only for non-empty text and emits the trimmed message', async () => {
		const wrapper = mountComposer()
		const sendButton = wrapper.get('button[aria-label="Send message"]')
		expect(sendButton.attributes('disabled')).toBeDefined()

		await wrapper.get('textarea').setValue('  Hello from Smart Picker  ')
		expect(sendButton.attributes('disabled')).toBeUndefined()
		await wrapper.get('form').trigger('submit')

		expect(wrapper.emitted('send')).toEqual([['Hello from Smart Picker']])
		expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('')
	})
})
