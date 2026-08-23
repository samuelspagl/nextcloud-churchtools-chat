import { describe, expect, it } from 'vitest'
import { backoffDelay } from '../../src/utils/backoff'

describe('backoffDelay', () => {
	it('uses the Retry-After delay when provided', () => {
		expect(backoffDelay(0, 42)).toBe(42_000)
	})

	it('clamps very large Retry-After values to one hour', () => {
		expect(backoffDelay(0, 99_999)).toBe(3_600_000)
	})

	it('grows exponentially with the attempt and stays within bounds', () => {
		const first = backoffDelay(0)
		const fourth = backoffDelay(3)

		expect(first).toBeGreaterThanOrEqual(1000)
		expect(first).toBeLessThan(2000)
		expect(fourth).toBeGreaterThanOrEqual(8000)
		expect(fourth).toBeLessThan(9000)
	})
})
