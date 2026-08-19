<template>
	<NcDialog
		:open="true"
		:name="dialogTitle"
		@update:open="$emit('close')">
		<p class="unlink-identity-modal__desc">
			{{ descriptionText }}
		</p>
		<NcCheckboxRadioSwitch
			:checked.sync="revoke"
			class="unlink-identity-modal__checkbox">
			{{ checkboxLabel }}
		</NcCheckboxRadioSwitch>
		<p class="unlink-identity-modal__checkbox-desc">
			{{ warningText }}
		</p>
		<p v-if="error" class="unlink-identity-modal__error">
			{{ error }}
		</p>
		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton type="error" :disabled="unlinking" @click="unlink">
				{{ unlinking ? t('olvid', 'Unlinking…') : (revoke ? t('olvid', 'Unlink and block') : t('olvid', 'Unlink')) }}
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
	name: 'UnlinkIdentityModal',
	components: { NcDialog, NcButton, NcCheckboxRadioSwitch },

	props: {
		// Required unless isSelf is true, since it is only used to build the
		// admin endpoint URL and to fill the other-user wording.
		user: {
			type: Object,
			default: null,
		},
		isSelf: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['close', 'unlinked'],

	data() {
		return {
			revoke: false,
			unlinking: false,
			error: null,
		}
	},

	computed: {
		dialogTitle() {
			return this.isSelf
				? t('olvid', 'I no longer have access to my Olvid profile')
				: t('olvid', 'Unlink Olvid identity — {name}', { name: this.user.displayName })
		},
		descriptionText() {
			return this.isSelf
				? t('olvid', 'This will disconnect your Olvid profile from this directory and allow you to register a new one.')
				: t('olvid', 'This will disconnect the Olvid profile linked to {name} from this directory and allow a new one to be registered.', { name: this.user.displayName })
		},
		checkboxLabel() {
			return this.isSelf
				? t('olvid', 'My Olvid profile was lost or replaced')
				: t('olvid', 'The Olvid profile of {name} was lost or replaced', { name: this.user.displayName })
		},
		warningText() {
			return this.isSelf
				? t('olvid', 'Warning: this action is not reversible. Your Olvid identity will be blocked for every other contact in this directory. They will no longer be able to reach you through this identity, and it can never be re-registered. You will need to create a new Olvid profile and re-enroll.')
				: t('olvid', 'Warning: this action is not reversible. The Olvid identity of {name} will be blocked for every other contact in this directory. Other contacts will no longer be able to reach {name} through this identity, and it can never be re-registered. A new Olvid profile will need to be created and re-enrolled.', { name: this.user.displayName })
		},
	},

	methods: {
		async unlink() {
			this.unlinking = true
			this.error = null
			try {
				const url = this.isSelf
					? generateOcsUrl('/apps/olvid/app/me/identity')
					: generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.user.id)}/identity`)
				await axios.delete(url, { data: { revoke: this.revoke } })
				this.$emit('unlinked', { revoke: this.revoke })
			} catch (e) {
				this.error = t('olvid', 'Could not unlink identity: {error}', { error: e.response?.data?.error ?? e.message })
			} finally {
				this.unlinking = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.unlink-identity-modal {
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
