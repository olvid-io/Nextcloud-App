<template>
	<NcAppContent>
		<NcEmptyContent :name="t('olvid', 'Users')" :description="t('olvid', 'Users will be shown here.')" />
	</NcAppContent>
</template>

<script lang="ts">
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

export default {
	name: 'UsersView',
	components: { NcAppContent, NcEmptyContent },
}
</script>
