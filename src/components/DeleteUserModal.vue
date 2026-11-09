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
			v-model="revoke"
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
			<NcButton variant="error" :disabled="deleting" @click="deleteUser">
				{{ deleting ? t('olvid', 'Deleting…') : t('olvid', 'Delete') }}
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
const revoke = ref(false)
const deleting = ref(false)
const error = ref(null)

async function deleteUser() {
	deleting.value = true
	error.value = null
	try {
		await users.remove(props.user.id, { revoke: props.user.useOlvid && revoke.value })
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
