<template>
	<NcAppSettingsDialog
		:open="open"
		:name="t('olvid', 'Olvid Settings')"
		@update:open="$emit('close')" />
</template>

<script>
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'

export default {
	name: 'OlvidAppSettings',
	components: { NcAppSettingsDialog },

	props: {
		open: {
			type: Boolean,
			default: false,
		},
	},
}

</script>

<style scoped lang="scss">

</style>
