import { readonly, shallowRef, toValue, watch } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { getErrorMessage, getGroupContext } from '../services/chatApi'
import type { GroupContextResponse } from '../types/chat'

interface UseGroupContextOptions {
	roomId: MaybeRefOrGetter<string>
	enabled: MaybeRefOrGetter<boolean>
}

export function useGroupContext(options: UseGroupContextOptions) {
	const context = shallowRef<GroupContextResponse | null>(null)
	const loading = shallowRef(false)
	const error = shallowRef('')
	const cache = new Map<string, GroupContextResponse>()
	let sequence = 0

	async function load(force = false) {
		const roomId = toValue(options.roomId)
		if (!toValue(options.enabled) || roomId === '') return
		const cached = cache.get(roomId)
		if (!force && cached) {
			context.value = cached
			error.value = ''
			return
		}

		const currentSequence = ++sequence
		loading.value = true
		error.value = ''
		if (force) context.value = null
		try {
			const response = await getGroupContext(roomId)
			if (currentSequence !== sequence || roomId !== toValue(options.roomId)) return
			cache.set(roomId, response)
			context.value = response
		} catch (caught) {
			if (currentSequence === sequence) {
				context.value = null
				error.value = getErrorMessage(caught)
			}
		} finally {
			if (currentSequence === sequence) loading.value = false
		}
	}

	function retry() {
		cache.delete(toValue(options.roomId))
		void load(true)
	}

	watch(
		() => [toValue(options.enabled), toValue(options.roomId)] as const,
		([enabled]) => {
			sequence += 1
			loading.value = false
			error.value = ''
			context.value = null
			if (enabled) void load()
		},
		{ immediate: true },
	)

	return {
		context: readonly(context),
		loading: readonly(loading),
		error: readonly(error),
		retry,
	}
}
