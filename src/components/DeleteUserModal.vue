<template>
	<NcDialog
		:open="true"
		:name="t('olvid', 'Delete the Nextcloud account of {name}', { name: user.displayName })"
		@update:open="$emit('close')">
		<p class="delete-user-modal__desc">
			{{ t('olvid', 'The account and all its data (files, calendars, contacts…) will be permanently deleted. This cannot be undone.') }}
		</p>

		<fieldset v-if="user.useOlvid" class="delete-user-modal__choices">
			<legend class="delete-user-modal__legend">
				{{ t('olvid', 'What should happen to the Olvid profile of {name}?', { name: user.displayName }) }}
			</legend>
			<NcCheckboxRadioSwitch
				v-model="mode"
				type="radio"
				value="revoke"
				name="delete-olvid-mode">
				{{ t('olvid', 'Block it (recommended)') }}
				<template #description>
					{{ t('olvid', 'The profile is blocked for all Nextcloud contacts, so nobody can keep using it to impersonate {name}. It can never be linked again.', { name: user.displayName }) }}
				</template>
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-model="mode"
				type="radio"
				value="unlink"
				name="delete-olvid-mode">
				{{ t('olvid', 'Only remove it from the directory') }}
				<template #description>
					{{ t('olvid', 'Contacts who already know this profile can still reach {name} with it.', { name: user.displayName }) }}
				</template>
			</NcCheckboxRadioSwitch>
		</fieldset>

		<p v-if="error" class="delete-user-modal__error">
			{{ error }}
		</p>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton variant="error" :disabled="deleting" @click="deleteUser">
				{{ deleting ? t('olvid', 'Deleting…') : t('olvid', 'Delete account') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { useUsersStore } from '../stores/users.js'

const props = defineProps({
	user: {
		type: Object,
		required: true,
	},
})

const emit = defineEmits(['close', 'deleted'])

const users = useUsersStore()
// same choice and default as UnlinkIdentityModal: block the Olvid profile unless told otherwise
const mode = ref('revoke')
const deleting = ref(false)
const error = ref(null)

async function deleteUser() {
	deleting.value = true
	error.value = null
	try {
		await users.remove(props.user.id, { revoke: props.user.useOlvid && mode.value === 'revoke' })
		emit('deleted')
	} catch (e) {
		error.value = t('olvid', 'Could not delete user: {error}', { error: e.response?.data?.error ?? e.message })
	} finally {
		deleting.value = false
	}
}
</script>

<style scoped lang="scss">
.delete-user-modal {
	&__desc {
		margin: 0;
		line-height: 1.5;
	}

	&__choices {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin: 16px 0 0;
		padding: 0;
		border: none;
	}

	&__legend {
		margin-bottom: 4px;
		font-weight: bold;
	}

	&__error {
		color: var(--color-error);
		margin: 8px 0 0;
	}
}
</style>
