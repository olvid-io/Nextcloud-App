<template>
	<NcDialog
		:name="t('olvid', 'Magic Link — {name}', { name: user.displayName })"
		:open="true"
		@update:open="$emit('close')">
		<div class="magic-link-modal">
			<OlvidQrDisplay :configurationUrl="configurationUrl" />
		</div>

		<template #actions>
			<NcButton variant="primary" @click="$emit('close')">
				{{ t('olvid', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import OlvidQrDisplay from './OlvidQrDisplay.vue'

export default {
	name: 'MagicLinkModal',
	components: { NcDialog, NcButton, OlvidQrDisplay },

	props: {
		user: {
			type: Object,
			required: true,
		},

		configurationUrl: {
			type: String,
			required: true,
		},
	},

	emits: ['close'],
}
</script>

<style scoped lang="scss">
.magic-link-modal {
	padding: 8px 0;
}
</style>
