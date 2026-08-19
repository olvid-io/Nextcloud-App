<template>
	<NcDialog
		:open="true"
		:name="t('olvid', 'Delete user')"
		@update:open="$emit('close')">
		<p class="delete-user-modal__desc">
			{{ t('olvid', 'Are you sure you want to delete {name}? This action cannot be undone.', { name: user.displayName }) }}
		</p>

		<NcCheckboxRadioSwitch
			v-if="user.useOlvid"
			:checked.sync="revoke"
			class="delete-user-modal__checkbox">
			{{ t('olvid', 'Also revoke the Olvid identity of {name}', { name: user.displayName }) }}
		</NcCheckboxRadioSwitch>
		<p v-if="user.useOlvid && revoke" class="delete-user-modal__checkbox-desc">
			{{ t('olvid', 'Warning: this action is not reversible. The Olvid identity of {name} will be blocked for every other contact in this directory. Other contacts will no longer be able to reach {name} through this identity, and it can never be re-registered.', { name: user.displayName }) }}
		</p>

		<p v-if="error" class="delete-user-modal__error">
			{{ error }}
		</p>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton type="error" :disabled="deleting" @click="deleteUser">
				{{ deleting ? t('olvid', 'Deleting…') : t('olvid', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

export default {
	name: 'DeleteUserModal',
	components: { NcDialog, NcButton, NcCheckboxRadioSwitch },

	props: {
		user: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'deleted'],

	data() {
		return {
			revoke: false,
			deleting: false,
			error: null,
		}
	},

	methods: {
		async deleteUser() {
			this.deleting = true
			this.error = null
			try {
				if (this.user.useOlvid && this.revoke) {
					await axios.delete(
						generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.user.id)}/identity`),
						{ data: { revoke: true } },
					)
				}
				await axios.delete(generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.user.id)}`))
				this.$emit('deleted', this.user.id)
			} catch (e) {
				this.error = t('olvid', 'Could not delete user: {error}', { error: e.response?.data?.error ?? e.message })
			} finally {
				this.deleting = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.delete-user-modal {
	&__desc {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}

	&__checkbox {
		margin-top: 12px;
	}

	&__checkbox-desc {
		margin: 4px 0 0;
		padding-inline-start: 28px;
		color: var(--color-text-maxcontrast);
		font-size: 0.875em;
		line-height: 1.4;
	}

	&__error {
		color: var(--color-error);
		margin: 8px 0 0;
	}
}
</style>
