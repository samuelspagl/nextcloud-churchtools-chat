import { getLanguage, t } from '@nextcloud/l10n'
import type { ChatMessage } from '../types/chat'

export const GROUP_TIME_GAP_MS = 5 * 60 * 1000
const DAY_MS = 86_400_000

export interface TimelineItem extends ChatMessage {
	showDateSeparator: boolean
	grouped: boolean
}

export function startOfDay(timestamp: number): number {
	const date = new Date(timestamp)
	date.setHours(0, 0, 0, 0)
	return date.getTime()
}

/**
 * Build the timeline decoration for a sorted message list: which messages start a
 * new day and which consecutive messages from the same sender are grouped.
 *
 * Grouping rules follow the Matrix/Nextcloud Talk convention: a message is grouped
 * with its predecessor only when the sender did not change, both are on the same
 * local calendar day, and the time gap stays below GROUP_TIME_GAP_MS.
 */
export function buildTimeline(messages: readonly ChatMessage[]): TimelineItem[] {
	const result: TimelineItem[] = []
	let previous: ChatMessage | null = null
	for (const message of messages) {
		const showDateSeparator = previous === null || startOfDay(previous.timestamp) !== startOfDay(message.timestamp)
		const grouped = !showDateSeparator
			&& previous !== null
			&& previous.sender === message.sender
			&& message.timestamp - previous.timestamp < GROUP_TIME_GAP_MS
		result.push({ ...message, showDateSeparator, grouped })
		previous = message
	}
	return result
}

const relativeDayFormatter = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'auto' })
const weekdayFormatter = new Intl.DateTimeFormat(getLanguage(), { weekday: 'long' })

function diffInDays(timestamp: number, now: number): number {
	return Math.round((startOfDay(timestamp) - startOfDay(now)) / DAY_MS)
}

function absoluteDayLabel(timestamp: number, now: number): string {
	const date = new Date(timestamp)
	const format: Intl.DateTimeFormatOptions = { month: 'long', day: 'numeric' }
	if (date.getFullYear() !== new Date(now).getFullYear()) {
		format.year = 'numeric'
	}
	return new Intl.DateTimeFormat(getLanguage(), format).format(date)
}

/**
 * Format a day separator label like "Today, March 18", "Yesterday, March 17",
 * "Friday, March 15" or just the absolute date for anything older than a week.
 * The year is included when it differs from the reference year.
 */
export function formatDayLabel(timestamp: number, now = Date.now()): string {
	const diff = diffInDays(timestamp, now)
	let relative = ''
	if (Math.abs(diff) <= 1) {
		relative = relativeDayFormatter.format(diff, 'day')
	} else if (Math.abs(diff) <= 6) {
		relative = weekdayFormatter.format(timestamp)
	}
	const absolute = absoluteDayLabel(timestamp, now)
	const label = relative
		? t('churchtools_chat', '{relativeDate}, {absoluteDate}', { relativeDate: relative, absoluteDate: absolute }, { escape: false })
		: absolute
	return label.charAt(0).toUpperCase() + label.slice(1)
}