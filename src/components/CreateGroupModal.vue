<template>
	<NcDialog
		:name="t('olvid', 'Create Group')"
		:open="true"
		@update:open="$emit('close')">
		<div class="create-group-modal">
			<NcTextField
				v-model="form.id"
				:label="t('olvid', 'Group Name')"
				:placeholder="t('olvid', 'e.g. members')"
				:required="true" />

			<p v-if="error" class="create-group-modal__error">
				{{ error }}
			</p>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || !form.id"
				@click="submit">
				{{ saving ? t('olvid', 'Creating…') : t('olvid', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'

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
		t,
		async submit() {
			if (!this.form.id) {
				return
			}
			this.saving = true
			this.error = null
			try {
				const res = await axios.post(generateOcsUrl('/apps/olvid/app/groups'), this.form)
				this.$emit('created', res.data)
				this.$router.push({ name: 'group-detail', params: { groupId: res.data.id } })
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
