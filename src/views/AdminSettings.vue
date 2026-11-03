<template>
	<NcSettingsSection
		:name="t('olvid', 'Olvid server')"
		:description="t('olvid', 'Configure the Olvid distribution server used by this Nextcloud instance.')">
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
			<NcTextField
				v-model="serverUrl"
				type="url"
				:label="t('olvid', 'Olvid Server url')"
				placeholder="https://server.example.io"
				:helperText="t('olvid', 'Edit only if you know what you are doing.')"
				:disabled="saving" />

			<NcPasswordField
				v-model="directoryKey"
				:label="t('olvid', 'Olvid Directory Key')"
				:placeholder="directoryKey ? t('olvid', '00000000-0000-0000-0000-000000000000') : ''"
				:helperText="t('olvid', 'Required to use Nextcloud Application, write to us an email to get one: contact@olvid.io')"
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

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const CONFIGURATION_URL = '/apps/olvid/app/admin/configuration'
const CONFIGURATION_SERVER_TEST_URL = '/apps/olvid/app/admin/configuration/server/test'

export default {
	name: 'AdminSettings',
	components: { NcButton, NcLoadingIcon, NcNoteCard, NcPasswordField, NcSettingsSection, NcTextField },

	data() {
		return {
			serverUrl: '',
			directoryKey: '',
			savedServerUrl: '',
			savedDirectoryKey: '',

			status: null,
			checking: false,
			saving: false,
			saveError: null,
		}
	},

	computed: {
		hasChanges() {
			return this.serverUrl !== this.savedServerUrl || this.directoryKey !== this.savedDirectoryKey
		},
	},

	async mounted() {
		await this.fetchConfiguration()
	},

	methods: {
		applyConfiguration(configuration) {
			this.serverUrl = configuration.server.serverUrl ?? ''
			this.savedServerUrl = this.serverUrl
			this.directoryKey = configuration.server.directoryKey ?? ''
			this.savedDirectoryKey = this.directoryKey
			this.status = { isConfigurationValid: configuration.server.isConfigurationValid, message: configuration.server.message }
		},

		async fetchConfiguration() {
			this.checking = true
			this.saveError = null
			try {
				const res = await axios.get(generateOcsUrl(CONFIGURATION_URL))
				this.applyConfiguration(res.data)
			} catch (e) {
				this.status = { isConfigurationValid: false, message: e.response?.data?.error ?? e.message }
			} finally {
				this.checking = false
			}
		},

		async testConfiguration() {
			this.checking = true
			this.saveError = null
			try {
				const res = await axios.post(generateOcsUrl(CONFIGURATION_SERVER_TEST_URL), { serverUrl: this.serverUrl, directoryKey: this.directoryKey })
				this.status = res.data
			} catch (e) {
				this.status = { isConfigurationValid: false, message: e.response?.data?.error ?? e.message }
			} finally {
				this.checking = false
			}
		},

		async save() {
			this.saving = true
			this.checking = true
			this.saveError = null
			try {
				const res = await axios.put(generateOcsUrl(CONFIGURATION_URL), {
					serverUrl: this.serverUrl,
					directoryKey: this.directoryKey,
				})
				this.directoryKey = ''
				this.applyConfiguration(res.data)
			} catch (e) {
				this.saveError = e.response?.data?.error ?? e.message
			} finally {
				this.saving = false
				this.checking = false
			}
		},
	},
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
</style>
