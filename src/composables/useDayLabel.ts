import { onBeforeUnmount, onMounted, readonly, ref } from 'vue'
import { formatDayLabel } from '../utils/timeline'

const REFRESH_INTERVAL_MS = 60_000

/**
 * Reactive day-separator labels. The "now" reference is refreshed periodically and on
 * window focus so labels stay correct when the app stays open across midnight.
 */
export function useDayLabel() {
	const now = ref(Date.now())
	let interval: number | undefined

	function refresh() {
		now.value = Date.now()
	}

	onMounted(() => {
		interval = window.setInterval(refresh, REFRESH_INTERVAL_MS)
		document.addEventListener('visibilitychange', refresh)
	})

	onBeforeUnmount(() => {
		if (interval !== undefined) window.clearInterval(interval)
		document.removeEventListener('visibilitychange', refresh)
	})

	function dayLabel(timestamp: number): string {
		return formatDayLabel(timestamp, now.value)
	}

	return { dayLabel, now: readonly(now) }
}