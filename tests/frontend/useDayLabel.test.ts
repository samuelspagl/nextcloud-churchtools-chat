// @vitest-environment happy-dom

import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useDayLabel } from '../../src/composables/useDayLabel'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	getLanguage: () => 'en-US',
}))

const TestComponent = defineComponent({
	setup() {
		return useDayLabel()
	},
	template: '<div />',
})

describe('useDayLabel', () => {
	beforeEach(() => {
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('formats the label for the current day', () => {
		vi.setSystemTime(new Date(2024, 2, 18, 15, 0, 0))
		const wrapper = mount(TestComponent)

		expect(wrapper.vm.dayLabel(new Date(2024, 2, 18, 9, 0, 0).getTime())).toBe('Today, March 18')
		wrapper.unmount()
	})

	it('refreshes the reference time periodically', () => {
		vi.setSystemTime(new Date(2024, 2, 18, 15, 0, 0))
		const wrapper = mount(TestComponent)
		const initial = wrapper.vm.now as number

		vi.advanceTimersByTime(61_000)

		expect(wrapper.vm.now).toBeGreaterThan(initial)
		wrapper.unmount()
	})
})