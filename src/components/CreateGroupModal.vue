<template>
	<NcDialog
		:name="t('olvid', 'Create Group')"
		:open="true"
		@update:open="$emit('close')">
		<div class="create-group-modal">
			<NcTextField
				:value.sync="form.id"
				:label="t('olvid', 'Group ID')"
				:placeholder="t('olvid', 'e.g. engineering')"
				:required="true" />

			<p v-if="error" class="create-group-modal__error">{{ error }}</p>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">{{ t('olvid', 'Cancel') }}</NcButton>
			<NcButton
				type="primary"
				:disabled="saving || !form.id"
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
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'CreateGroupModal',
	components: { NcDialog, NcButton, NcTextField },

	emits: ['close', 'created'],

	data() {
		return {
			form: {
				id: '',
			},
			saving: false,
			error: null,
		}
	},

	methods: {
		async submit() {
			if (!this.form.id) return
			this.saving = true
			this.error = null
			try {
				const res = await axios.post(generateOcsUrl('/apps/olvid/app/groups'), this.form)
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
.create-group-modal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 4px 0;

	&__error {
		color: var(--color-error-text);
		margin: 0;
	}
}
</style>
