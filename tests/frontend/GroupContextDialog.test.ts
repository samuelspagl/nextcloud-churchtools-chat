// @vitest-environment happy-dom

import { shallowMount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import type { GroupContextResponse } from '../../src/types/chat'
import GroupContextDialog from '../../src/components/GroupContextDialog.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...await importOriginal<typeof import('@nextcloud/l10n')>(),
	translate: (_app: string, text: string) => text,
}))

const context: GroupContextResponse = {
	matchStatus: 'matched',
	group: {
		id: 17,
		name: 'Technik',
		visibility: 'internal',
		groupType: 'Dienstgruppe',
		category: 'Technik',
		description: '<script>alert(1)</script> Sound and lights',
		frontendUrl: 'https://tenant.church.tools/?q=groups#/17',
		leadership: [{ roleId: 8, roleName: 'Bereichsleitung', members: [{ personId: 42, displayName: 'Anna Schmidt', avatarUrl: null }] }],
		memberCount: 12,
	},
	nextcloud: {
		status: 'matched',
		teams: [{
			id: 'team-1',
			name: 'Technik',
			url: '/apps/teams/team-1',
			resources: [
				{ id: 'folder-1', kind: 'folder', label: 'Shared folder', url: '/f/1' },
				{ id: 'board-1', kind: 'deck-board', label: 'Planning', url: '/apps/deck/board/1' },
			],
		}],
	},
}

const DialogStub = defineComponent({ template: '<div class="dialog"><slot /></div>' })
const ButtonStub = defineComponent({
	inheritAttrs: false,
	props: { href: String, target: String },
	template: '<a v-if="href" :href="href" :target="target" v-bind="$attrs"><slot /></a><button v-else v-bind="$attrs"><slot /></button>',
})
const AvatarStub = defineComponent({ props: { displayName: String }, template: '<span class="avatar">{{ displayName }}</span>' })
const IconStub = defineComponent({ props: { svg: String }, template: '<span class="icon" />' })
const NoteStub = defineComponent({ props: { text: String }, template: '<div>{{ text }}<slot /></div>' })

function mountDialog(value = context) {
	return shallowMount(GroupContextDialog, {
		props: { context: value },
		global: { stubs: { NcDialog: DialogStub, NcButton: ButtonStub, NcAvatar: AvatarStub, NcIconSvgWrapper: IconStub, NcNoteCard: NoteStub } },
	})
}

describe('GroupContextDialog', () => {
	it('shows group fields, flattened leadership, and linked resources', () => {
		const wrapper = mountDialog()

		expect(wrapper.text()).toContain('Dienstgruppe')
		expect(wrapper.text()).toContain('12')
		expect(wrapper.text()).toContain('Anna Schmidt')
		expect(wrapper.text()).toContain('Shared folder')
		expect(wrapper.text()).toContain('Planning')
		expect(wrapper.find('script').exists()).toBe(false)
		expect(wrapper.get('a[href="https://tenant.church.tools/?q=groups#/17"]').attributes('target')).toBe('_blank')
		expect(wrapper.get('a[href="/apps/deck/board/1"]').attributes('rel')).toBe('noopener noreferrer')
	})

	it('shows a retry action for a partial Nextcloud failure', async () => {
		const wrapper = mountDialog({ ...context, nextcloud: { status: 'error', teams: [] } })

		await wrapper.get('button').trigger('click')
		expect(wrapper.emitted('retry')).toHaveLength(1)
	})
})
