<template>
	<NcAppNavigation>
		<NcAppNavigationCaption :name="t('olvid', 'Olvid Console')" />

		<NcAppNavigationItem
			:name="t('olvid', 'Profile')"
			:active="$route.name === 'profile'"
			@click="$router.push({ name: 'profile' })">
			<template #icon>
				<IconAccount :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			v-if="isAdmin"
			:name="t('olvid', 'Users')"
			:active="$route.name === 'users' || $route.name === 'user-detail'"
			@click="$router.push({ name: 'users' })">
			<template #icon>
				<IconAccountMultiple :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			v-if="isAdmin"
			:name="t('olvid', 'Groups')"
			:active="$route.name === 'groups' || $route.name === 'group-detail'"
			@click="$router.push({ name: 'groups' })">
			<template #icon>
				<IconAccountGroup :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			v-if="isAdmin"
			:name="t('olvid', 'Statistics')"
			:active="$route.name === 'statistics'"
			@click="$router.push({ name: 'statistics' })">
			<template #icon>
				<IconChartBar :size="20" />
			</template>
		</NcAppNavigationItem>

		<template #footer>
			<ul class="app-navigation-entry__settings">
				<NcAppNavigationItem
					v-if="isAdmin"
					:name="t('olvid', 'Olvid Settings')"
					data-cy-olvid-navigation-settings-button
					@click="settingsOpened = !settingsOpened">
					<template #icon>
						<IconCog :size="20" />
					</template>
				</NcAppNavigationItem>
			</ul>
		</template>

		<OlvidAppSettings
			v-model:open="settingsOpened"
			data-cy-olvid-navigation-settings />
	</NcAppNavigation>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { ref } from 'vue'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import IconAccountGroup from 'vue-material-design-icons/AccountGroupOutline.vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultipleOutline.vue'
import IconAccount from 'vue-material-design-icons/AccountOutline.vue'
import IconChartBar from 'vue-material-design-icons/ChartBar.vue'
import IconCog from 'vue-material-design-icons/CogOutline.vue'
import OlvidAppSettings from './components/OlvidAppSettings.vue'
import { useMeStore } from './stores/me.js'

// hides admin entries only, not a security boundary (endpoints are admin-only server-side)
const { isAdmin } = storeToRefs(useMeStore())
const settingsOpened = ref(false)
</script>

<style scoped lang="css">

.app-navigation-entry__settings {
	height: auto !important;
	overflow: hidden !important;
	padding-top: 0 !important;
	flex: 0 0 auto;
}

</style>
