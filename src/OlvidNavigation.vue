<template>
	<NcAppNavigation>
		<NcAppNavigationCaption name="Olvid Console" />

		<NcAppNavigationItem
			:name="t('olvid', 'Profile')"
			:active="$route.name === 'profile'"
			@click="$router.push({ name: 'profile' }).catch(() => {})">
			<template #icon>
				<IconAccount :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			v-if="isAdmin"
			:name="t('olvid', 'Users')"
			:active="$route.name === 'users' || $route.name === 'user-detail'"
			@click="$router.push({ name: 'users' }).catch(() => {})">
			<template #icon>
				<IconAccountMultiple :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			v-if="isAdmin"
			:name="t('olvid', 'Groups')"
			:active="$route.name === 'groups' || $route.name === 'group-detail'"
			@click="$router.push({ name: 'groups' }).catch(() => {})">
			<template #icon>
				<IconAccountGroup :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			v-if="isAdmin"
			:name="t('olvid', 'Statistics')"
			:active="$route.name === 'statistics'"
			@click="$router.push({ name: 'statistics' }).catch(() => {})">
			<template #icon>
				<IconChartBar :size="20" />
			</template>
		</NcAppNavigationItem>

		<template #footer>
			<ul class="app-navigation-entry__settings">
				<NcAppNavigationItem
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
			data-cy-olvid-navigation-settings
			:open.sync="settingsOpened"
			@close="settingsOpened = false" />
	</NcAppNavigation>
</template>

<script>
import IconAccount from 'vue-material-design-icons/AccountOutline.vue'
import IconAccountGroup from 'vue-material-design-icons/AccountGroupOutline.vue'
import IconCog from 'vue-material-design-icons/CogOutline.vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultipleOutline.vue'
import IconChartBar from 'vue-material-design-icons/ChartBar.vue'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import OlvidAppSettings from './components/OlvidAppSettings.vue'

export default {
	name: 'OlvidNavigation',
	components: { NcAppNavigation, NcAppNavigationCaption, NcAppNavigationItem, IconAccount, IconAccountGroup, IconCog, IconAccountMultiple, IconChartBar, OlvidAppSettings },
	props: {
		isAdmin: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			settingsOpened: false,
		}
	},
}
</script>

<style scoped lang="css">

.app-navigation-entry__settings {
	height: auto !important;
	overflow: hidden !important;
	padding-top: 0 !important;
	flex: 0 0 auto;
}

</style>
