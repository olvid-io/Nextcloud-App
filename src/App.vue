<template>
	<NcContent app-name="olvid">
		<OlvidNavigation />
		<router-view
			ref="view"
			@open-group-sidebar="onOpenGroupSidebar" />

		<GroupDetailsSidebar
			v-if="selectedGroup"
			:group="selectedGroup"
			@close="onSidebarClose"
			@updated="onGroupUpdated" />
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import GroupDetailsSidebar from './components/GroupDetailsSidebar.vue'
import OlvidNavigation from './OlvidNavigation.vue'

export default {
	name: 'App',
	components: { NcContent, OlvidNavigation, GroupDetailsSidebar },

	data() {
		return {
			selectedGroup: null,
		}
	},

	watch: {
		$route(to) {
			if (to.name !== 'groups' && to.name !== 'group-detail') {
				this.selectedGroup = null
			}
		},
	},

	methods: {
		onOpenGroupSidebar(group) {
			this.selectedGroup = group
			this.$router.push({ name: 'group-detail', params: { groupId: group.id } }).catch(() => {})
		},

		onSidebarClose() {
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
	},
}
</script>
