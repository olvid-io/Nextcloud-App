<template>
	<NcDialog
		:open="true"
		:name="dialogTitle"
		@update:open="$emit('close')">
		<p class="unlink-identity-modal__desc">
			{{ descriptionText }}
		</p>
		<fieldset class="unlink-identity-modal__choices">
			<legend class="unlink-identity-modal__legend">
				{{ legendText }}
			</legend>
			<NcCheckboxRadioSwitch
				v-model="mode"
				type="radio"
				value="revoke"
				name="unlink-mode">
				{{ revokeLabel }}
				<template #description>
					{{ revokeDescription }}
				</template>
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-model="mode"
				type="radio"
				value="unlink"
				name="unlink-mode">
				{{ unlinkLabel }}
				<template #description>
					{{ unlinkDescription }}
				</template>
			</NcCheckboxRadioSwitch>
		</fieldset>
		<p v-if="revoke" class="unlink-identity-modal__warning">
			{{ t('olvid', 'This cannot be undone.') }}
		</p>
		<p v-if="isSelf" class="unlink-identity-modal__next">
			{{ t('olvid', 'Afterwards, you will be guided to link a new Olvid profile.') }}
		</p>
		<p v-if="error" class="unlink-identity-modal__error">
			{{ error }}
		</p>
		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton :variant="revoke ? 'error' : 'primary'" :disabled="unlinking" @click="unlink">
				{{ unlinking ? t('olvid', 'Unlinking…') : (revoke ? t('olvid', 'Block and unlink') : t('olvid', 'Unlink')) }}
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
// 'revoke': old identity is lost → block it everywhere (safe default)
// 'unlink': old identity is still owned → only detach it from this directory
const mode = ref('revoke')
const revoke = computed(() => mode.value === 'revoke')
const unlinking = ref(false)
const error = ref(null)

const dialogTitle = computed(() => props.isSelf
	? t('olvid', 'Unlink my Olvid profile')
	: t('olvid', 'Unlink Olvid profile of {name}', { name: props.user.displayName }))

const descriptionText = computed(() => props.isSelf
	? t('olvid', 'Your current Olvid profile will be removed from this directory so that you can link a new one.')
	: t('olvid', 'The Olvid profile linked to {name} will be removed from this directory so that a new one can be linked.', { name: props.user.displayName }))

const legendText = computed(() => props.isSelf
	? t('olvid', 'What happened to your current Olvid profile?')
	: t('olvid', 'What happened to the current Olvid profile of {name}?', { name: props.user.displayName }))

const revokeLabel = computed(() => props.isSelf
	? t('olvid', 'I lost it (recommended)')
	: t('olvid', 'It was lost (recommended)'))

const revokeDescription = computed(() => props.isSelf
	? t('olvid', 'Lost or stolen phone, app uninstalled, profile deleted… Your old profile is blocked for all your Nextcloud contacts, so nobody can use it to impersonate you. It can never be linked again.')
	: t('olvid', 'Lost or stolen phone, app uninstalled, profile deleted… The old profile is blocked for all Nextcloud contacts, so nobody can use it to impersonate {name}. It can never be linked again.', { name: props.user.displayName }))

const unlinkLabel = computed(() => props.isSelf
	? t('olvid', 'I still have it')
	: t('olvid', '{name} still has it', { name: props.user.displayName }))

const unlinkDescription = computed(() => props.isSelf
	? t('olvid', 'You simply want to use another profile. Your old profile is only removed from this directory; contacts who already know it can still reach you with it.')
	: t('olvid', 'The old profile is only removed from this directory; contacts who already know it can still reach {name} with it.', { name: props.user.displayName }))

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

	&__warning {
		margin: 12px 0 0;
		color: var(--color-error-text, var(--color-error));
		font-weight: bold;
	}

	&__next {
		margin: 12px 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__error {
		color: var(--color-error);
		margin: 8px 0 0;
	}
}
</style>
