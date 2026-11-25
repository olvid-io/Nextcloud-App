<template>
	<NcDialog
		:name="t('olvid', 'Invalid application configuration')"
		:noClose="true"
		:buttons="buttons">
		<p class="invalid-configuration__message">
			<template v-if="isAdmin">
				{{ t('olvid', 'Before using the app, configure your license in the Olvid section of the administration settings.') }}
			</template>
			<template v-else>
				{{ t('olvid', 'The Olvid app is not configured yet. Please contact your Nextcloud administrator.') }}
			</template>
		</p>
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

<style scoped lang="scss">
.invalid-configuration__message {
	margin: 0;
	color: var(--color-error-text, var(--color-error));
	font-weight: bold;
}
</style>
