<template>
	<NcDialog
		:name="t('olvid', 'Invalid application configuration')"
		:noClose="true"
		:buttons="buttons">
		<div style="color: var('--color-error'); font-weight: bold;">
			<div v-if="isAdmin">
				{{ t('olvid', 'You must configure a License in Olvid section of Administration settings before starting using the app.') }}
			</div>
			<div v-else>
				{{ t('olvid', 'Application configuration is not valid. Contact your Nextcloud administrator to fix it.') }}
			</div>
		</div>
	</NcDialog>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'

const props = defineProps({
	isAdmin: {
		type: Boolean,
		required: true,
	},
})

function openOlvidSettings() {
	window.open(generateUrl('/settings/admin/olvid'), '_self')
}

function openHomePage() {
	window.open(generateUrl('/'), '_self')
}

// admins get an extra button to open admin settings
const buttons = computed(() => props.isAdmin
	? [
			{ callback: openHomePage, label: t('olvid', 'Home') },
			{ callback: openOlvidSettings, label: t('olvid', 'Admin settings'), variant: 'primary' },
		]
	: [
			{ callback: openHomePage, label: t('olvid', 'Home'), variant: 'primary' },
		])
</script>
