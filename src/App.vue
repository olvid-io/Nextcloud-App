<template>
	<NcContent app-name="olvid">
		<OlvidNavigation :current-tab="currentTab" @select="currentTab = $event" />
		<ProfileView v-if="currentTab === 'profile'" />
		<GroupsView v-else-if="currentTab === 'groups'" />
		<UsersView v-else-if="currentTab === 'users'" />
	</NcContent>
</template>

<script lang="ts">
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import GroupsView from './views/GroupsView.vue'
import OlvidNavigation from './OlvidNavigation.vue'
import ProfileView from './views/ProfileView.vue'
import UsersView from './views/UsersView.vue'

export default {
	name: 'App',
	components: { NcContent, OlvidNavigation, ProfileView, GroupsView, UsersView },
	data() {
		return { currentTab: 'profile' }
	},
}
</script>
