import { describe, expect, it, vi } from 'vitest'
import type { ChatMessage } from '../../src/types/chat'
import { buildTimeline, formatDayLabel, GROUP_TIME_GAP_MS } from '../../src/utils/timeline'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	getLanguage: () => 'en-US',
}))

const DAY_MS = 86_400_000

function message(overrides: Partial<ChatMessage> = {}): ChatMessage {
	return {
		id: `$${overrides.timestamp ?? 0}`,
		sender: '@anna:test',
		body: 'Hello',
		timestamp: new Date(2024, 2, 18, 12, 0, 0).getTime(),
		...overrides,
	}
}

describe('buildTimeline', () => {
	it('marks the first message with a date separator and no grouping', () => {
		const [first] = buildTimeline([message()])

		expect(first.showDateSeparator).toBe(true)
		expect(first.grouped).toBe(false)
	})

	it('groups consecutive messages from the same sender within the time gap', () => {
		const first = message()
		const second = message({ id: '$2', timestamp: first.timestamp + 60_000 })

		const [a, b] = buildTimeline([first, second])

		expect(a.showDateSeparator).toBe(true)
		expect(b.showDateSeparator).toBe(false)
		expect(b.grouped).toBe(true)
	})

	it('does not group messages from different senders', () => {
		const first = message()
		const second = message({ id: '$2', sender: '@ben:test', timestamp: first.timestamp + 60_000 })

		const [a, b] = buildTimeline([first, second])

		expect(b.showDateSeparator).toBe(false)
		expect(b.grouped).toBe(false)
	})

	it('breaks the group when the time gap reaches the limit', () => {
		const first = message()
		const within = message({ id: '$2', timestamp: first.timestamp + 60_000 })
		const atLimit = message({ id: '$3', timestamp: within.timestamp + GROUP_TIME_GAP_MS })

		const [, grouped, ungrouped] = buildTimeline([first, within, atLimit])

		expect(grouped.grouped).toBe(true)
		expect(ungrouped.grouped).toBe(false)
	})

	it('starts a new day and breaks grouping when the calendar day changes', () => {
		const late = message({ timestamp: new Date(2024, 2, 18, 23, 50).getTime() })
		const nextMorning = message({ id: '$2', timestamp: new Date(2024, 2, 19, 0, 10).getTime() })

		const [a, b] = buildTimeline([late, nextMorning])

		expect(b.showDateSeparator).toBe(true)
		expect(b.grouped).toBe(false)
	})

	it('keeps messages on the same day grouped even when near midnight', () => {
		const first = message({ timestamp: new Date(2024, 2, 18, 23, 50).getTime() })
		const second = message({ id: '$2', timestamp: first.timestamp + 60_000 })

		const [, b] = buildTimeline([first, second])

		expect(b.showDateSeparator).toBe(false)
		expect(b.grouped).toBe(true)
	})
})

describe('formatDayLabel', () => {
	const now = new Date(2024, 2, 18, 15, 0, 0).getTime()

	it('labels the current day as Today', () => {
		expect(formatDayLabel(now, now)).toBe('Today, March 18')
	})

	it('labels the previous day as Yesterday', () => {
		const yesterday = new Date(2024, 2, 17, 10, 0, 0).getTime()
		expect(formatDayLabel(yesterday, now)).toBe('Yesterday, March 17')
	})

	it('uses the weekday for days within a week', () => {
		const threeDaysAgo = new Date(2024, 2, 15, 10, 0, 0).getTime()
		expect(formatDayLabel(threeDaysAgo, now)).toBe('Friday, March 15')
	})

	it('falls back to the absolute date beyond a week', () => {
		const eightDaysAgo = now - 8 * DAY_MS
		expect(formatDayLabel(eightDaysAgo, now)).toBe('March 10')
	})

	it('includes the year for dates in a different year', () => {
		const lastYear = new Date(2023, 11, 31, 10, 0, 0).getTime()
		expect(formatDayLabel(lastYear, now)).toBe('December 31, 2023')
	})
})