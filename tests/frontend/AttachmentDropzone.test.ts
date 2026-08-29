// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import AttachmentDropzone from '../../src/components/AttachmentDropzone.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

function mountDropzone(disabled = false) {
	return mount(AttachmentDropzone, {
		props: { disabled },
		slots: { default: '<div class="content">Timeline</div>' },
	})
}

describe('AttachmentDropzone', () => {
	it('activates the overlay with a localized, icon-based hint when a file drag enters, and emits filesDropped on drop', async () => {
		const wrapper = mountDropzone()

		expect(wrapper.find('.dropzone__overlay').exists()).toBe(false)

		await wrapper.get('.dropzone').trigger('dragenter', { dataTransfer: { types: ['Files'] } })
		expect(wrapper.find('.dropzone__overlay').exists()).toBe(true)
		expect(wrapper.get('.dropzone__overlay').attributes('aria-label')).toBe('Drop files here to add them to your message')
		expect(wrapper.get('.dropzone__badge').find('.icon-vue').exists()).toBe(true)
		expect(wrapper.text()).not.toContain('Drop to send')

		const file = new File(['content'], 'photo.png', { type: 'image/png' })
		await wrapper.get('.dropzone').trigger('drop', { dataTransfer: { types: ['Files'], files: [file] } })

		expect(wrapper.find('.dropzone__overlay').exists()).toBe(false)
		const emitted = wrapper.emitted('filesDropped')
		expect(emitted).toHaveLength(1)
		expect((emitted?.[0]?.[0] as FileList)[0]).toBe(file)
	})

	it('ignores a drag that does not carry files', async () => {
		const wrapper = mountDropzone()

		await wrapper.get('.dropzone').trigger('dragenter', { dataTransfer: { types: ['text/plain'] } })
		expect(wrapper.find('.dropzone__overlay').exists()).toBe(false)

		await wrapper.get('.dropzone').trigger('drop', { dataTransfer: { types: ['text/plain'], files: [] } })
		expect(wrapper.emitted('filesDropped')).toBeUndefined()
	})

	it('does not activate or emit while disabled', async () => {
		const wrapper = mountDropzone(true)

		await wrapper.get('.dropzone').trigger('dragenter', { dataTransfer: { types: ['Files'] } })
		expect(wrapper.find('.dropzone__overlay').exists()).toBe(false)

		const file = new File(['content'], 'photo.png', { type: 'image/png' })
		await wrapper.get('.dropzone').trigger('drop', { dataTransfer: { types: ['Files'], files: [file] } })
		expect(wrapper.emitted('filesDropped')).toBeUndefined()
	})
})
