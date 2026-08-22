import { generateUrl } from '@nextcloud/router'

export function attachmentThumbnailUrl(mxcUrl: string): string {
	// Keep failed responses from the former media endpoint out of the browser cache.
	return `${generateUrl('/apps/churchtools_chat/api/media/thumbnail')}?mxc=${encodeURIComponent(mxcUrl)}&v=2`
}

export function attachmentDownloadUrl(mxcUrl: string, filename: string): string {
	return `${generateUrl('/apps/churchtools_chat/api/media/download')}?mxc=${encodeURIComponent(mxcUrl)}&filename=${encodeURIComponent(filename)}`
}

export function attachmentViewUrl(mxcUrl: string): string {
	return `${generateUrl('/apps/churchtools_chat/api/media/view')}?mxc=${encodeURIComponent(mxcUrl)}`
}

export function formatFileSize(size: number | null): string {
	if (size === null || size < 0) return ''
	const units = ['B', 'KB', 'MB', 'GB']
	let value = size
	let unit = 0
	while (value >= 1024 && unit < units.length - 1) {
		value /= 1024
		unit++
	}
	return `${new Intl.NumberFormat(undefined, { maximumFractionDigits: unit === 0 ? 0 : 1 }).format(value)} ${units[unit]}`
}
