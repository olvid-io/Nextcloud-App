<template>
	<NcDialog
		v-if="show"
		:name="t('olvid', 'Invalid application configuration')"
		:buttons="buttons">
		<div style="color: var('--color-error'); font-weight: bold;">
			<div v-if="isAdmin">
				{{ t('olvid', 'You must configure an Olvid server API key in Administration settings before starting using the app.') }}
			</div>
			<div v-else>
				{{ t('olvid', 'Application configuration is not valid. Contact your Nextcloud administrator to fix it.') }}
			</div>
		</div>
	</NcDialog>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import NcDialog from '@nextcloud/vue/components/NcDialog'

export default {
	name: 'InvalidConfigurationAlertModal',
	components: { NcDialog },

	props: {
		isAdmin: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		const buttons = []
		// if user is admin add a button to open admin settings
		if (this.isAdmin) {
			buttons.push({ callback: this.openOlvidSettings, label: t('olvid', 'Go to admin settings') })
		}
		return {
			show: true,
			buttons,
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
