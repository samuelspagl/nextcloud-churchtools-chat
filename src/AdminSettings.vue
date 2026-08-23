<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { computed, onMounted, shallowRef } from 'vue'
import { getAdminSettings, getErrorMessage, saveAdminSettings } from './services/chatApi'
import type { AdminSettingsState } from './types/chat'

const churchToolsTenantUrl = shallowRef('')
const matrixServerUrl = shallowRef('https://chat.church.tools')
const saving = shallowRef(false)
const loaded = shallowRef(false)
const saveDisabled = computed(() => saving.value
	|| !loaded.value
	|| churchToolsTenantUrl.value.trim() === '')

onMounted(async () => {
	try {
		const state = await getAdminSettings()
		churchToolsTenantUrl.value = state.churchToolsTenantUrl
		matrixServerUrl.value = state.matrixServerUrl
		loaded.value = true
	} catch (error) {
		showError(getErrorMessage(error))
	}
})

async function save() {
	saving.value = true
	try {
		const state: AdminSettingsState = await saveAdminSettings(churchToolsTenantUrl.value, matrixServerUrl.value)
		churchToolsTenantUrl.value = state.churchToolsTenantUrl
		matrixServerUrl.value = state.matrixServerUrl
		showSuccess(t('churchtools_chat', 'ChurchTools Chat server settings saved.'))
	} catch (error) {
		showError(getErrorMessage(error))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<NcSettingsSection
		:name="t('churchtools_chat', 'ChurchTools Chat')"
		:description="t('churchtools_chat', 'Configure the servers used by ChurchTools Chat. These settings apply to all users; each user still connects with their own access token and CT Chat password.')">
		<div class="settings-form">
			<NcTextField
				v-model="churchToolsTenantUrl"
				:label="t('churchtools_chat', 'ChurchTools tenant URL')"
				autocomplete="url"
				:helper-text="t('churchtools_chat', 'HTTPS URL of the ChurchTools tenant, e.g. https://efg-darmstadt.church.tools')" />
			<NcTextField
				v-model="matrixServerUrl"
				:label="t('churchtools_chat', 'Matrix homeserver URL')"
				autocomplete="url"
				:helper-text="t('churchtools_chat', 'HTTPS URL of the Matrix homeserver, e.g. https://chat.church.tools')" />
			<div class="settings-form__actions">
				<NcButton
					variant="primary"
					:disabled="saveDisabled"
					@click="save">
					{{ saving ? t('churchtools_chat', 'Saving…') : t('churchtools_chat', 'Save') }}
				</NcButton>
			</div>
		</div>
	</NcSettingsSection>
</template>

<style scoped>
.settings-form { display: grid; max-width: 640px; gap: 16px; }
.settings-form__actions { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
