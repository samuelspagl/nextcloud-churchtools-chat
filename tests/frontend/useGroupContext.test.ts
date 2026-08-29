// @vitest-environment happy-dom

import { flushPromises } from '@vue/test-utils'
import { shallowRef } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { GroupContextResponse } from '../../src/types/chat'

const mockedGetGroupContext = vi.fn()

vi.mock('../../src/services/chatApi', () => ({
	getGroupContext: (...args: unknown[]) => mockedGetGroupContext(...args),
	getErrorMessage: (error: unknown) => error instanceof Error ? error.message : 'failed',
}))

import { useGroupContext } from '../../src/composables/useGroupContext'

const none: GroupContextResponse = {
	matchStatus: 'none',
	group: null,
	nextcloud: { status: 'unavailable', teams: [] },
}

describe('useGroupContext', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('loads only when enabled and caches the response per room', async () => {
		const roomId = shallowRef('!one:server')
		const enabled = shallowRef(false)
		mockedGetGroupContext.mockResolvedValue(none)
		const state = useGroupContext({ roomId, enabled })

		enabled.value = true
		await flushPromises()
		expect(mockedGetGroupContext).toHaveBeenCalledTimes(1)
		expect(state.context.value).toEqual(none)

		enabled.value = false
		await flushPromises()
		enabled.value = true
		await flushPromises()
		expect(mockedGetGroupContext).toHaveBeenCalledTimes(1)
	})

	it('ignores a stale response after switching rooms', async () => {
		const roomId = shallowRef('!one:server')
		const enabled = shallowRef(true)
		let resolveFirst: (value: GroupContextResponse) => void = () => undefined
		mockedGetGroupContext
			.mockReturnValueOnce(new Promise<GroupContextResponse>((resolve) => { resolveFirst = resolve }))
			.mockResolvedValueOnce(none)
		const state = useGroupContext({ roomId, enabled })

		roomId.value = '!two:server'
		await flushPromises()
		resolveFirst({ ...none, matchStatus: 'ambiguous' })
		await flushPromises()

		expect(mockedGetGroupContext).toHaveBeenNthCalledWith(2, '!two:server')
		expect(state.context.value?.matchStatus).toBe('none')
	})

	it('exposes an error and retries without using the cache', async () => {
		const roomId = shallowRef('!one:server')
		const enabled = shallowRef(true)
		mockedGetGroupContext.mockRejectedValueOnce(new Error('Unavailable')).mockResolvedValueOnce(none)
		const state = useGroupContext({ roomId, enabled })

		await flushPromises()
		expect(state.error.value).toBe('Unavailable')
		state.retry()
		await flushPromises()

		expect(mockedGetGroupContext).toHaveBeenCalledTimes(2)
		expect(state.context.value).toEqual(none)
	})
})
