<template>
	<NcDialog
		v-if="show"
		:name="t('olvid', 'Invalid application configuration')"
		:buttons="[
			{callback: openOlvidSettings, label: t('olvid', 'Go to admin settings')},
			{callback: close, label: t('olvid', 'Dismiss'), variant: 'error'},]">
		<div style="color: var('--color-error'); font-weight: bold;">
			{{ t('olvid', 'You shall configure an Olvid server API key in Administration settings before starting using the app.') }}
		</div>
	</NcDialog>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

export default {
	name: 'InvalidConfigurationAlertModal',
	components: { NcDialog },
	data() {
		return {
			show: true,
		}
	},

	methods: {
		close() {
			this.show = false
		},
		getOlvidSettingsUrl() {
			return generateUrl('/settings/admin/olvid')
		},
		openOlvidSettings() {
			window.open(this.getOlvidSettingsUrl(), '_self')
		},
	},
}
</script>

<style scoped lang="scss">

</style>
