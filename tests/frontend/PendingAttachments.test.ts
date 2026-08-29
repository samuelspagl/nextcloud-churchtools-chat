// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PendingAttachments from '../../src/components/PendingAttachments.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

describe('PendingAttachments', () => {
	it('renders a chip per staged file with a thumbnail for images', () => {
		const image = new File(['content'], 'photo.png', { type: 'image/png' })
		const pdf = new File(['content'], 'notes.pdf', { type: 'application/pdf' })
		const wrapper = mount(PendingAttachments, { props: { files: [image, pdf] } })

		const chips = wrapper.findAll('.pending-attachment')
		expect(chips).toHaveLength(2)
		expect(chips[0].find('img.pending-attachment__thumb').exists()).toBe(true)
		expect(chips[0].text()).toContain('photo.png')
		expect(chips[1].find('img.pending-attachment__thumb').exists()).toBe(false)
		expect(chips[1].find('.pending-attachment__icon').text()).toBe('PDF')
		expect(chips[1].text()).toContain('notes.pdf')
	})

	it('emits remove with the correct file when its chip button is clicked', async () => {
		const image = new File(['content'], 'photo.png', { type: 'image/png' })
		const pdf = new File(['content'], 'notes.pdf', { type: 'application/pdf' })
		const wrapper = mount(PendingAttachments, {
			props: { files: [image, pdf] },
			global: { stubs: { NcIconSvgWrapper: true } },
		})

		const removeButtons = wrapper.findAll('.pending-attachment__remove')
		await removeButtons[1].trigger('click')

		expect(wrapper.emitted('remove')).toEqual([[pdf]])
	})
})
