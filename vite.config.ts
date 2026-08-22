/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'
import type { UserConfig as VitestUserConfig } from 'vitest/config'

const testConfig = {
	test: {
		environment: 'happy-dom',
		server: {
			deps: {
				inline: ['@nextcloud/vue', '@nextcloud/dialogs'],
			},
		},
	},
} satisfies VitestUserConfig

export default createAppConfig({
	main: 'src/main.ts',
	settings: 'src/settings.ts',
}, {
	config: testConfig,
})
