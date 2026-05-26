<template>
	<NcAppNavigation>
		<NcAppNavigationItem
			name="Profile"
			:active="currentTab === 'profile'"
			@click="$emit('select', 'profile')" />
		<NcAppNavigationItem
			name="Groups"
			:active="currentTab === 'groups'"
			@click="$emit('select', 'groups')" />
		<NcAppNavigationItem
			name="Users"
			:active="currentTab === 'users'"
			@click="$emit('select', 'users')" />
	</NcAppNavigation>
</template>

<script lang="ts">
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'

export default {
	name: 'OlvidNavigation',
	components: { NcAppNavigation, NcAppNavigationItem },
	props: {
		currentTab: {
			type: String,
			default: 'profile',
		},
	},
	emits: ['select'],
}
</script>
