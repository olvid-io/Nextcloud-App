<template>
	<NcDialog
		:name="t('olvid', 'Magic Link - {name}', { name: user.displayName })"
		:open="true"
		size="small"
		@update:open="$emit('close')">

		<p>
			{{ t('olvid', 'Send this link to {name} to allow them to connect an Olvid profile to it\'s Nextcloud account.', { name: user.displayName }) }}
		</p>

		<div class="magic-link-modal">
			<NcNoteCard
				class="magic-link-modal__notecard"
				type="warning"
				:text="t('olvid', 'This secure link is strictly personal.')" />
		</div>

		<p class="magic-link-modal__hint">
			{{ t('olvid', 'The link will expire in 24 hours') }}
		</p>

		<template #actions>
			<NcButton @click="openLink">
				<template #icon>
					<IconOpenInNew :size="20" />
				</template>
				{{ t('olvid', 'Open') }}
			</NcButton>
			<NcButton variant="primary" @click="copyLink">
				<template #icon>
					<IconCheck v-if="copied" :size="20" />
					<IconContentCopy v-else :size="20" />
				</template>
				{{ copied ? t('olvid', 'Link copied!') : t('olvid', 'Copy link') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'

const props = defineProps({
	user: {
		type: Object,
		required: true,
	},

	configurationUrl: {
		type: String,
		required: true,
	},
})

defineEmits(['close'])

const copied = ref(false)

function openLink() {
	window.open(props.configurationUrl, '_blank', 'noopener')
}

async function copyLink() {
	try {
		await navigator.clipboard.writeText(props.configurationUrl)
		copied.value = true
		setTimeout(() => {
			copied.value = false
		}, 2500)
	} catch (e) {
		console.error('Could not copy to clipboard', e)
		showError(t('olvid', 'Could not copy to clipboard'))
	}
}
</script>

<style scoped lang="scss">
.magic-link-modal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 8px 0;

	&__notecard {
		margin: 0;
		padding: 0;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}
}
</style>
