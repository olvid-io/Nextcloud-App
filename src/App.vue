<template>
	<NcContent appName="olvid">
		<InvalidConfigurationAlertModal v-if="me.configurationValid === false" :isAdmin="me.isAdmin" />

		<OlvidNavigation />
		<router-view />

		<!-- Sidebars are driven by the route: they only receive an id and read the stores -->
		<GroupDetailsSidebar
			v-if="route.name === 'group-detail'"
			:groupId="route.params.groupId" />

		<UserDetailsSidebar
			v-if="route.name === 'user-detail'"
			:userId="route.params.userId" />
	</NcContent>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import NcContent from '@nextcloud/vue/components/NcContent'
import GroupDetailsSidebar from './components/GroupDetailsSidebar.vue'
import InvalidConfigurationAlertModal from './components/InvalidConfigurationAlertModal.vue'
import UserDetailsSidebar from './components/UserDetailsSidebar.vue'
import OlvidNavigation from './OlvidNavigation.vue'
import { useMeStore } from './stores/me.js'

const me = useMeStore()
const route = useRoute()

onMounted(async () => {
	try {
		await me.load()
		// check in background if Olvid application configuration is valid or not
		await me.checkConfiguration()
	} catch (e) {
		console.error('Could not fetch user', e)
		showError(t('olvid', 'Cannot load user'))
	}
})
</script>
