<template>
	<NcDialog
		:name="t('olvid', 'Create User')"
		:open="true"
		@update:open="$emit('close')">
		<div class="create-user-modal">
			<NcTextField
				:value.sync="form.uid"
				:label="t('olvid', 'Nextcloud user ID')"
				:placeholder="t('olvid', 'e.g. jsmith')"
				:required="true" />

			<details class="create-user-modal__details">
				<summary class="create-user-modal__summary">
					{{ t('olvid', 'Olvid details (optional)') }}
				</summary>
				<div class="create-user-modal__olvid-fields">
					<NcTextField
						:value.sync="form.firstname"
						:label="t('olvid', 'First name')" />
					<NcTextField
						:value.sync="form.lastname"
						:label="t('olvid', 'Last name')" />
					<NcTextField
						:value.sync="form.position"
						:label="t('olvid', 'Position')" />
					<NcTextField
						:value.sync="form.company"
						:label="t('olvid', 'Company')" />
				</div>
			</details>

			<details class="create-user-modal__details">
				<summary class="create-user-modal__summary">
					{{ t('olvid', 'Nextcloud password (optional)') }}
				</summary>
				<div class="create-user-modal__olvid-fields">
					<div>{{ t('olvid', 'Leave blank if this user will only use Olvid and not Nextcloud.') }}</div>
					<div>{{ t('olvid', 'You can set a password later in Nextcloud account settings if you change your mind.') }}</div>

					<NcPasswordField
						:value.sync="form.password"
						:label="t('olvid', 'Password')"
						:required="true" />
				</div>
			</details>

			<div class="create-user-modal__item">
				<NcSelect
					v-model="form.groups"
					class="create-user-modal__select"
					:input-label="t('olvid', 'Member of the following groups')"
					:placeholder="t('olvid', 'Set user groups')"
					:options="allGroupIds"
					keep-open
					:multiple="true" />
			</div>

			<p v-if="error" class="create-user-modal__error">{{ error }}</p>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">{{ t('olvid', 'Cancel') }}</NcButton>
			<NcButton
				type="primary"
				:disabled="saving || !form.uid"
				@click="submit">
				{{ saving ? t('olvid', 'Creating…') : t('olvid', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'CreateUserModal',
	components: { NcDialog, NcButton, NcTextField, NcPasswordField, NcSelect },

	emits: ['close', 'created'],

	data() {
		return {
			form: {
				uid: '',
				password: '',
				firstname: '',
				lastname: '',
				position: '',
				company: '',
				groups: [],
			},
			allGroupIds: [],
			saving: false,
			error: null,
		}
	},

	async mounted() {
		await this.fetchGroups()
	},

	methods: {
		async submit() {
			if (!this.form.uid) return
			this.saving = true
			this.error = null
			try {
				const res = await axios.post(generateOcsUrl('/apps/olvid/app/users'), this.form)
				this.$emit('created', res.data)
				this.$router.push({ name: 'user-detail', params: { userId: res.data.id } }).catch(() => {})
				this.$emit('close')
			} catch (e) {
				this.error = e.response?.data?.error ?? e.message
			} finally {
				this.saving = false
			}
		},

		/*
		** Groups
		 */
		async fetchGroups() {
			this.loading = true
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/groups'))
				this.allGroupIds = res.data.groups.map(g => g.id) ?? []
			} catch (e) {
				console.error('Could not load groups', e)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.create-user-modal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 4px 0;

	&__details {
		margin-top: 4px;
	}

	&__summary {
		cursor: pointer;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
		user-select: none;
	}

	&__olvid-fields {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin-top: 8px;
	}

	&__error {
		color: var(--color-error-text);
		margin: 0;
	}
}
</style>
