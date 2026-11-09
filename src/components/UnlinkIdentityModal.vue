<template>
	<NcDialog
		:open="true"
		:name="dialogTitle"
		@update:open="$emit('close')">
		<p class="unlink-identity-modal__desc">
			{{ descriptionText }}
		</p>
		<NcCheckboxRadioSwitch
			v-model="revoke"
			class="unlink-identity-modal__checkbox">
			{{ checkboxLabel }}
		</NcCheckboxRadioSwitch>
		<p v-if="revoke" class="unlink-identity-modal__checkbox-desc">
			{{ warningText }}
		</p>
		<p v-if="error" class="unlink-identity-modal__error">
			{{ error }}
		</p>
		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton variant="error" :disabled="unlinking" @click="unlink">
				{{ unlinking ? t('olvid', 'Unlinking…') : (revoke ? t('olvid', 'Unlink and block') : t('olvid', 'Unlink')) }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { useMeStore } from '../stores/me.js'
import { useUsersStore } from '../stores/users.js'

const props = defineProps({
	user: {
		type: Object,
		required: true,
	},

	isSelf: {
		type: Boolean,
		default: false,
	},
})

const emit = defineEmits(['close', 'unlinked'])

const me = useMeStore()
const users = useUsersStore()
const revoke = ref(true)
const unlinking = ref(false)
const error = ref(null)

const dialogTitle = computed(() => props.isSelf
	? t('olvid', 'I no longer have access to my Olvid profile')
	: t('olvid', 'Unlink Olvid identity — {name}', { name: props.user.displayName }))

const descriptionText = computed(() => props.isSelf
	? t('olvid', 'This will disconnect your Olvid profile from this directory and allow you to register a new one.')
	: t('olvid', 'This will disconnect the Olvid profile linked to {name} from this directory and allow a new one to be registered.', { name: props.user.displayName }))

const checkboxLabel = computed(() => props.isSelf
	? t('olvid', 'My Olvid profile was lost or replaced')
	: t('olvid', 'The Olvid profile of {name} was lost or replaced', { name: props.user.displayName }))

const warningText = computed(() => props.isSelf
	? t('olvid', 'Warning: this action is not reversible. Your Olvid identity will be blocked for every other contact in this directory. They will no longer be able to reach you through this identity, and it can never be re-registered. You will need to create a new Olvid profile and re-enroll.')
	: t('olvid', 'Warning: this action is not reversible. The Olvid identity of {name} will be blocked for every other contact in this directory. Other contacts will no longer be able to reach {name} through this identity, and it can never be re-registered. A new Olvid profile will need to be created and re-enrolled.', { name: props.user.displayName }))

async function unlink() {
	unlinking.value = true
	error.value = null
	try {
		if (props.isSelf) {
			await me.unlink(revoke.value)
		} else {
			await users.unlinkIdentity(props.user.id, revoke.value)
		}
		emit('unlinked', { revoke: revoke.value })
	} catch (e) {
		error.value = t('olvid', 'Could not unlink identity: {error}', { error: e.response?.data?.error ?? e.message })
	} finally {
		unlinking.value = false
	}
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
