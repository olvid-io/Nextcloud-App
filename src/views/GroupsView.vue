<template>
	<NcAppContent>
		<NcEmptyContent :name="t('olvid', 'Groups')" :description="t('olvid', 'Groups will be shown here.')" />
	</NcAppContent>
</template>

<script lang="ts">
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'GroupsView',
	components: { NcAppContent, NcEmptyContent },
}
</script>
