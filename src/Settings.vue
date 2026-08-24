<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import { computed, onMounted, shallowRef } from 'vue'
import { deleteSettings, getErrorMessage, getSettings, saveSettings } from './services/chatApi'
import type { IntegrationError, SettingsState } from './types/chat'

const token = shallowRef('')
const matrixPassword = shallowRef('')
const state = shallowRef<SettingsState | null>(null)
const bootstrapError = shallowRef<IntegrationError | null>(null)
const saving = shallowRef(false)
const saveDisabled = computed(() => saving.value
	|| (!state.value?.configured && token.value.trim() === '')
	|| (state.value?.configured && token.value.trim() === '' && matrixPassword.value === ''))

onMounted(async () => {
	try {
		state.value = await getSettings()
	} catch (error) {
		showError(getErrorMessage(error))
	}
})

async function save() {
	saving.value = true
	bootstrapError.value = null
	try {
		state.value = await saveSettings(token.value, matrixPassword.value)
		bootstrapError.value = state.value.bootstrapError ?? null
		token.value = ''
		if (state.value.matrixConnected) {
			matrixPassword.value = ''
		}
		if (bootstrapError.value) {
			showError(bootstrapError.value.message)
		} else {
			showSuccess(t('churchtools_chat', 'ChurchTools Chat settings saved and validated.'))
		}
	} catch (error) {
		showError(getErrorMessage(error))
	} finally {
		saving.value = false
	}
}

async function disconnect() {
	try {
		await deleteSettings()
		state.value = null
		bootstrapError.value = null
		token.value = ''
		matrixPassword.value = ''
		showSuccess(t('churchtools_chat', 'ChurchTools Chat disconnected.'))
	} catch (error) {
		showError(getErrorMessage(error))
	}
}
</script>

<template>
	<NcSettingsSection
		:name="t('churchtools_chat', 'ChurchTools Chat')"
		:description="t('churchtools_chat', 'Connect your own ChurchTools account. Secret values are encrypted and are never shown again after saving.')">
		<NcNoteCard type="warning">
			{{ t('churchtools_chat', 'This is an experimental app. It may change, be incomplete, or behave unexpectedly; use it with care.') }}
		</NcNoteCard>
		<div class="settings-form">
			<NcPasswordField v-model="token" :label="t('churchtools_chat', 'ChurchTools access token')" autocomplete="new-password" />
			<NcPasswordField
				v-model="matrixPassword"
				:label="t('churchtools_chat', 'CT Chat password')"
				autocomplete="new-password"
				:help="t('churchtools_chat', 'The Matrix username is derived from your ChurchTools GUID. The password is used once to obtain a Matrix access token and is not stored.')" />
			<div class="settings-form__actions">
				<NcButton
					variant="primary"
					:disabled="saveDisabled"
					@click="save">
					{{ saving ? t('churchtools_chat', 'Connecting…') : t('churchtools_chat', 'Connect') }}
				</NcButton>
				<NcButton v-if="state?.configured" variant="error" @click="disconnect">{{ t('churchtools_chat', 'Disconnect') }}</NcButton>
			</div>
			<NcNoteCard v-if="state && !state.tenantUrl" type="warning">
				{{ t('churchtools_chat', 'The administrator has not configured the ChurchTools server yet. Ask your administrator to set the ChurchTools tenant URL in the app settings.') }}
			</NcNoteCard>
			<NcNoteCard v-if="state?.configured" type="success">
				{{ t('churchtools_chat', 'Connected as') }} {{ state.displayName }} {{ t('churchtools_chat', 'to') }} {{ state.tenantUrl }}.
			</NcNoteCard>
			<NcNoteCard v-if="state?.matrixConnected" type="success">
				{{ t('churchtools_chat', 'CT Chat connected as') }} {{ state.matrixUserId }}.
			</NcNoteCard>
			<NcNoteCard v-if="state?.configured && state.canChat === false" type="warning">
				{{ t('churchtools_chat', 'ChurchTools reports canChat=false for your account. The app will still try to connect CT Chat.') }}
			</NcNoteCard>
			<NcNoteCard v-if="bootstrapError" type="warning">
				{{ bootstrapError.message }} {{ t('churchtools_chat', 'ChurchTools remains connected, but messages cannot be loaded until a valid CT Chat password is supplied.') }}
			</NcNoteCard>
		</div>
	</NcSettingsSection>
</template>

<style scoped>
.settings-form { display: grid; max-width: 640px; gap: 16px; }
.settings-form__actions { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
