import { t } from '@nextcloud/l10n'

export interface TypingUser {
	id: string
	displayName: string
}

export function typingLabel(users: readonly TypingUser[]): string {
	if (users.length === 0) return ''
	const names = users.map((user) => user.displayName)
	if (names.length === 1) {
		return t('churchtools_chat', '{name} is typing…', { name: names[0] })
	}
	if (names.length === 2) {
		return t('churchtools_chat', '{firstName} and {secondName} are typing…', {
			firstName: names[0],
			secondName: names[1],
		})
	}
	return t('churchtools_chat', '{firstName}, {secondName} and {count} more are typing…', {
		firstName: names[0],
		secondName: names[1],
		count: String(names.length - 2),
	})
}