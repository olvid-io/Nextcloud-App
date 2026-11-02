<template>
	<NcAppSettingsDialog
		:open="open"
		:name="t('olvid', 'Olvid Settings')"
		showNavigation
		@update:open="$emit('update:open', $event)">
		<NcAppSettingsSection v-if="isAdmin" id="admin-settings" :name="t('olvid', 'Admin settings')">
			<NcButton
				:href="getOlvidSettingsUrl()"
				target="_blank">
				{{ t('olvid', 'Open Olvid admin settings') }}
			</NcButton>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'OlvidAppSettings',
	components: { NcAppSettingsDialog, NcAppSettingsSection, NcButton },

	props: {
		open: {
			type: Boolean,
			default: false,
		},

		isAdmin: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:open'],

	methods: {
		getOlvidSettingsUrl() {
			return generateUrl('/settings/admin/olvid')
		},
	},
}

</script>
