<template>
	<NcAppSettingsDialog
		v-model:open="open"
		:name="t('olvid', 'Olvid Settings')"
		showNavigation>
		<NcAppSettingsSection v-if="isAdmin" id="admin-settings" :name="t('olvid', 'Admin settings')">
			<NcButton
				:href="olvidSettingsUrl"
				target="_blank">
				{{ t('olvid', 'Open Olvid admin settings') }}
			</NcButton>
		</NcAppSettingsSection>
	</NcAppSettingsDialog>
</template>

<script setup>
import { generateUrl } from '@nextcloud/router'
import { storeToRefs } from 'pinia'
import NcAppSettingsDialog from '@nextcloud/vue/components/NcAppSettingsDialog'
import NcAppSettingsSection from '@nextcloud/vue/components/NcAppSettingsSection'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useMeStore } from '../stores/me.js'

const open = defineModel('open', { type: Boolean, default: false })
const { isAdmin } = storeToRefs(useMeStore())
const olvidSettingsUrl = generateUrl('/settings/admin/olvid')
</script>
