<template>
	<NcAppNavigation>
		<NcAppNavigationCaption name="Olvid Console" />

		<NcAppNavigationItem
			:name="t('olvid', 'Profile')"
			:active="$route.name === 'profile'"
			@click="$router.push({ name: 'profile' }).catch(() => {})">
			<template #icon>
				<AccountIcon :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			:name="t('olvid', 'Groups')"
			:active="$route.name === 'groups' || $route.name === 'group-detail'"
			@click="$router.push({ name: 'groups' }).catch(() => {})">
			<template #icon>
				<AccountGroupIcon :size="20" />
			</template>
		</NcAppNavigationItem>

		<NcAppNavigationItem
			:name="t('olvid', 'Users')"
			:active="$route.name === 'users'"
			@click="$router.push({ name: 'users' }).catch(() => {})">
			<template #icon>
				<AccountMultipleIcon :size="20" />
			</template>
		</NcAppNavigationItem>
	</NcAppNavigation>
</template>

<script>
import AccountIcon from 'vue-material-design-icons/Account.vue'
import AccountGroupIcon from 'vue-material-design-icons/AccountGroup.vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

export default {
	name: 'OlvidNavigation',
	components: { NcAppNavigation, NcAppNavigationCaption, NcAppNavigationItem, AccountIcon, AccountGroupIcon, AccountMultipleIcon },
}
</script>
