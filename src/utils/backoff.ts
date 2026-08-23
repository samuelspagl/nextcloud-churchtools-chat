export function backoffDelay(attempt: number, retryAfter?: number | null): number {
	if (retryAfter && retryAfter > 0) {
		return Math.min(Math.max(retryAfter, 1), 3600) * 1000
	}
	const exponent = Math.min(5, Math.max(0, attempt))
	const base = 1000 * 2 ** exponent
	const jitter = Math.random() * 1000
	return Math.round(base + jitter)
}
