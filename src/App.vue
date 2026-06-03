<template>
	<NcContent app-name="olvid">
		<OlvidNavigation :is-admin="isAdmin" />
		<router-view
			ref="view"
			@open-group-sidebar="onOpenGroupSidebar"
			@open-user-sidebar="onOpenUserSidebar" />

		<GroupDetailsSidebar
			v-if="selectedGroup"
			:group="selectedGroup"
			@close="onGroupSidebarClose"
			@updated="onGroupUpdated" />

		<UserDetailsSidebar
			v-if="selectedUser"
			:user="selectedUser"
			@close="onUserSidebarClose"
			@updated="onUserUpdated"
			@deleted="onUserDeleted" />
	</NcContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import GroupDetailsSidebar from './components/GroupDetailsSidebar.vue'
import UserDetailsSidebar from './components/UserDetailsSidebar.vue'
import OlvidNavigation from './OlvidNavigation.vue'

export default {
	name: 'App',
	components: { NcContent, OlvidNavigation, GroupDetailsSidebar, UserDetailsSidebar },

	data() {
		return {
			isAdmin: false,
			selectedGroup: null,
			selectedUser: null,
		}
	},

	watch: {
		$route(to) {
			if (to.name !== 'groups' && to.name !== 'group-detail') {
				this.selectedGroup = null
			}
			if (to.name !== 'users' && to.name !== 'user-detail') {
				this.selectedUser = null
			}
		},
	},

	async mounted() {
		try {
			const res = await axios.get(generateOcsUrl('/apps/olvid/app/me'))
			this.isAdmin = res.data.isAdmin ?? false
		} catch (e) {
			console.error('Could not fetch user status', e)
		}
	},

	methods: {
		// ── Groups ──────────────────────────────────────────────────────────
		onOpenGroupSidebar(group) {
			this.selectedGroup = group
			this.$router.push({ name: 'group-detail', params: { groupId: group.id } }).catch(() => {})
		},

		onGroupSidebarClose() {
			this.selectedGroup = null
			this.$router.push({ name: 'groups' }).catch(() => {})
		},

		onGroupUpdated(patch) {
			if (this.selectedGroup && this.selectedGroup.id === patch.id) {
				this.selectedGroup = { ...this.selectedGroup, ...patch }
			}
			if (this.$refs.view && typeof this.$refs.view.applyGroupPatch === 'function') {
				this.$refs.view.applyGroupPatch(patch)
			}
		},

		// ── Users ────────────────────────────────────────────────────────────
		onOpenUserSidebar(user) {
			this.selectedUser = user
			this.$router.push({ name: 'user-detail', params: { userId: user.id } }).catch(() => {})
		},

		onUserSidebarClose() {
			this.selectedUser = null
			this.$router.push({ name: 'users' }).catch(() => {})
		},

		onUserUpdated(patch) {
			if (this.selectedUser && this.selectedUser.id === patch.id) {
				this.selectedUser = { ...this.selectedUser, ...patch }
			}
			if (this.$refs.view && typeof this.$refs.view.applyUserPatch === 'function') {
				this.$refs.view.applyUserPatch(patch)
			}
		},

		onUserDeleted(userId) {
			this.selectedUser = null
			this.$router.push({ name: 'users' }).catch(() => {})
			if (this.$refs.view && typeof this.$refs.view.removeUser === 'function') {
				this.$refs.view.removeUser(userId)
			}
		},
	},
}
</script>
