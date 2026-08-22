import { generateUrl } from '@nextcloud/router'

const MXC_URI_PATTERN = /^mxc:\/\/[A-Za-z0-9.\-:[\]]{1,255}\/[A-Za-z0-9_-]{1,255}$/

export function displayableAvatarUrl(url: string | null | undefined): string | undefined {
	if (!url) return undefined
	if (MXC_URI_PATTERN.test(url)) {
		return `${generateUrl('/apps/churchtools_chat/api/avatar')}?mxc=${encodeURIComponent(url)}`
	}
	try {
		const parsed = new URL(url, window.location.origin)
		return parsed.protocol === 'https:' || parsed.protocol === 'http:' ? parsed.href : undefined
	} catch {
		return undefined
	}
}
