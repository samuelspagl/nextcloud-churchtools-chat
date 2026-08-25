import { describe, expect, it, vi } from 'vitest'
import { typingLabel } from '../../src/utils/typing'

vi.mock('@nextcloud/l10n', async (importOriginal) => {
	const actual = await importOriginal<typeof import('@nextcloud/l10n')>()
	return {
		...actual,
		t: (_app: string, text: string, params?: Record<string, unknown>) => {
			let result = text
			for (const [key, value] of Object.entries(params ?? {})) {
				result = result.replaceAll(`{${key}}`, String(value))
			}
			return result
		},
	}
})

describe('typingLabel', () => {
	it('returns an empty label without users', () => {
		expect(typingLabel([])).toBe('')
	})

	it('labels a single typing user', () => {
		expect(typingLabel([{ id: '@a:test', displayName: 'Anna' }])).toBe('Anna is typing…')
	})

	it('labels two typing users', () => {
		expect(typingLabel([
			{ id: '@a:test', displayName: 'Anna' },
			{ id: '@b:test', displayName: 'Ben' },
		])).toBe('Anna and Ben are typing…')
	})

	it('labels more than two typing users with a count', () => {
		expect(typingLabel([
			{ id: '@a:test', displayName: 'Anna' },
			{ id: '@b:test', displayName: 'Ben' },
			{ id: '@c:test', displayName: 'Clara' },
		])).toBe('Anna, Ben and 1 more are typing…')
	})
})