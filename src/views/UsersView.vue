<template>
	<NcAppContent>
		<NcHeaderMenu id="users-header" description="Users" />
		<div class="users-view">
			<NcEmptyContent :name="t('olvid', 'Users')" :description="t('olvid', 'Users will be shown here.')" />
		</div>
	</NcAppContent>
</template>

<script lang="ts">
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcHeaderMenu from '@nextcloud/vue/dist/Components/NcHeaderMenu.js'

export default {
	name: 'UsersView',
	components: { NcHeaderMenu, NcAppContent, NcEmptyContent },
}
</script>
