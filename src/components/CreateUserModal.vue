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
			<NcPasswordField
				:value.sync="form.password"
				:label="t('olvid', 'Password')"
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

			<p v-if="error" class="create-user-modal__error">{{ error }}</p>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">{{ t('olvid', 'Cancel') }}</NcButton>
			<NcButton
				type="primary"
				:disabled="saving || !form.uid || !form.password"
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
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'CreateUserModal',
	components: { NcDialog, NcButton, NcTextField, NcPasswordField },

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
			},
			saving: false,
			error: null,
		}
	},

	methods: {
		async submit() {
			if (!this.form.uid || !this.form.password) return
			this.saving = true
			this.error = null
			try {
				const res = await axios.post(generateOcsUrl('/apps/olvid/app/users'), this.form)
				this.$emit('created', res.data)
				this.$emit('close')
			} catch (e) {
				this.error = e.response?.data?.error ?? e.message
			} finally {
				this.saving = false
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
