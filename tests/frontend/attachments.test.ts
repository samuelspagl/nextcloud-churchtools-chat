import { describe, expect, it } from 'vitest'
import { formatFileSize } from '../../src/utils/attachments'

describe('attachment helpers', () => {
	it('formats known attachment sizes for the file card', () => {
		expect(formatFileSize(1536)).toBe('1.5 KB')
		expect(formatFileSize(null)).toBe('')
	})
})
