<template>
	<NcSettingsSection
		:name="t('olvid', 'Directory License')"
		:description="t('olvid', 'An Olvid Directory license is mandatory to use Nextcloud application.\nWrite to us an email to get one: contact@olvid.io.')">
		<NcNoteCard v-if="checking" type="info">
			<div class="admin-settings__checking">
				<NcLoadingIcon :size="20" />
				{{ t('olvid', 'Checking configuration…') }}
			</div>
		</NcNoteCard>
		<NcNoteCard v-else-if="status" :type="status.isConfigurationValid ? 'success' : 'error'">
			{{ status.message }}
		</NcNoteCard>

		<form class="admin-settings__form" @submit.prevent="save">
			<NcPasswordField
				v-model="directoryKey"
				:label="t('olvid', 'Directory License Key')"
				:placeholder="directoryKey ? t('olvid', '00000000-0000-0000-0000-000000000000') : ''"
				:disabled="saving" />

			<NcTextField
				v-model="serverUrl"
				type="url"
				:label="t('olvid', 'Olvid Server url')"
				placeholder="https://server.example.io"
				:helperText="t('olvid', 'Edit only if you know what you are doing.')"
				:disabled="saving" />

			<p v-if="saveError" class="admin-settings__error">
				{{ saveError }}
			</p>

			<div class="admin-settings__actions">
				<NcButton type="submit" variant="primary" :disabled="!hasChanges || saving || checking">
					{{ t('olvid', 'Save') }}
				</NcButton>
				<NcButton :disabled="saving || checking" @click="testConfiguration">
					{{ t('olvid', 'Test configuration') }}
				</NcButton>
			</div>
		</form>
	</NcSettingsSection>
</template>

<script setup>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const CONFIGURATION_URL = '/apps/olvid/app/admin/configuration'
const CONFIGURATION_SERVER_TEST_URL = '/apps/olvid/app/admin/configuration/server/test'

const serverUrl = ref('')
const directoryKey = ref('')
const savedServerUrl = ref('')
const savedDirectoryKey = ref('')

const status = ref(null)
const checking = ref(false)
const saving = ref(false)
const saveError = ref(null)

const hasChanges = computed(() => serverUrl.value !== savedServerUrl.value || directoryKey.value !== savedDirectoryKey.value)

onMounted(fetchConfiguration)

function applyConfiguration(configuration) {
	serverUrl.value = configuration.server.serverUrl ?? ''
	savedServerUrl.value = serverUrl.value
	directoryKey.value = configuration.server.directoryKey ?? ''
	savedDirectoryKey.value = directoryKey.value
	status.value = { isConfigurationValid: configuration.server.isConfigurationValid, message: configuration.server.message }
}

async function fetchConfiguration() {
	checking.value = true
	saveError.value = null
	try {
		const res = await axios.get(generateOcsUrl(CONFIGURATION_URL))
		applyConfiguration(res.data)
	} catch (e) {
		status.value = { isConfigurationValid: false, message: e.response?.data?.error ?? e.message }
	} finally {
		checking.value = false
	}
}

async function testConfiguration() {
	checking.value = true
	saveError.value = null
	try {
		const res = await axios.post(generateOcsUrl(CONFIGURATION_SERVER_TEST_URL), { serverUrl: serverUrl.value, directoryKey: directoryKey.value })
		status.value = res.data
	} catch (e) {
		status.value = { isConfigurationValid: false, message: e.response?.data?.error ?? e.message }
	} finally {
		checking.value = false
	}
}

async function save() {
	saving.value = true
	checking.value = true
	saveError.value = null
	try {
		const res = await axios.put(generateOcsUrl(CONFIGURATION_URL), {
			serverUrl: serverUrl.value,
			directoryKey: directoryKey.value,
		})
		directoryKey.value = ''
		applyConfiguration(res.data)
	} catch (e) {
		saveError.value = e.response?.data?.error ?? e.message
	} finally {
		saving.value = false
		checking.value = false
	}
}
</script>

<style scoped lang="scss">
.admin-settings__form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
	max-width: 500px;
}

.admin-settings__checking {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
}

.admin-settings__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 2);
}

.admin-settings__error {
	color: var(--color-error-text);
}

:deep(.settings-section__desc) {
	white-space: pre-line;
}
</style>
