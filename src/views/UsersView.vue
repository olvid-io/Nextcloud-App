<template>
	<NcAppContent>
		<div class="users-view__header">
			<div class="users-view__heading">
				<h2 class="users-view__title">
					{{ t('olvid', 'Users') }}
				</h2>
				<NcButton variant="primary" @click="showCreateModal = true">
					<template #icon>
						<IconPlus :size="20" />
					</template>
					{{ t('olvid', 'Create user') }}
				</NcButton>
			</div>
			<div class="users-view__intro">
				<p class="users-view__desc">
					{{ t('olvid', 'Every Nextcloud user is listed here, whether they use Olvid or not. The badge on each avatar shows who has linked their profile. Users who have linked their Olvid profile appear in the Olvid directory: other users can find them and add them as contacts in Olvid.') }}
				</p>
				<p class="users-view__desc">
					{{ t('olvid', 'To help a user link their Olvid profile, send them their magic link. It explains how to install Olvid and link their profile in a few steps.') }}
				</p>
				<p class="users-view__desc">
					{{ t('olvid', 'Open a user to edit how they appear in Olvid, manage their groups or unlink their Olvid profile.') }}
				</p>
			</div>
		</div>

		<div class="users-view">
			<!-- only show the spinner on first load, later fetches refresh the list in place -->
			<NcLoadingIcon v-if="!users.loaded" :size="44" />

			<NcEmptyContent
				v-else-if="!users.list.length"
				:name="t('olvid', 'No users')"
				:description="t('olvid', 'No Nextcloud users found.')" />

			<ul v-else class="users-view__list">
				<NcListItem
					v-for="user in users.list"
					:key="user.id"
					:name="user.displayName"
					:active="$route.params.userId === user.id"
					@click="openUser(user)">
					<template #icon>
						<OlvidAvatar :user="user.id"
							:displayName="user.displayName"
							:useOlvid="user.useOlvid"
							:isInactive="user.isInactive" />
					</template>

					<template v-if="user.id !== user.displayName" #subname>
						{{ user.id }}
					</template>

					<template #extra-actions>
						<NcButton
							v-if="!user.useOlvid"
							size="small"
							variant="secondary"
							:disabled="loadingMagicLink === user.id"
							@click.stop="openMagicLink(user)">
							{{ t('olvid', 'Magic link') }}
						</NcButton>
						<NcButton
							size="small"
							variant="tertiary"
							:aria-label="t('olvid', 'Details')"
							:title="t('olvid', 'Details')"
							@click.stop="openUser(user)">
							<template #icon>
								<IconPencil :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>

		<!-- Magic link modal -->
		<MagicLinkModal
			v-if="magicLinkTarget"
			:user="magicLinkTarget"
			:configurationUrl="magicLinkUrl"
			@close="magicLinkTarget = null" />

		<!-- Create user modal -->
		<CreateUserModal
			v-if="showCreateModal"
			@close="showCreateModal = false" />
	</NcAppContent>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import CreateUserModal from '../components/CreateUserModal.vue'
import MagicLinkModal from '../components/MagicLinkModal.vue'
import OlvidAvatar from '../components/OlvidAvatar.vue'
import { useUsersStore } from '../stores/users.js'

const users = useUsersStore()
const router = useRouter()

const magicLinkTarget = ref(null)
const magicLinkUrl = ref(null)
const loadingMagicLink = ref(null)
const showCreateModal = ref(false)

onMounted(async () => {
	try {
		await users.fetchAll()
	} catch (e) {
		console.error('Could not load users', e)
		showError(t('olvid', 'Could not load users'))
	}
})

function openUser(user) {
	router.push({ name: 'user-detail', params: { userId: user.id } })
}

async function openMagicLink(user) {
	loadingMagicLink.value = user.id
	try {
		magicLinkUrl.value = await users.getMagicLink(user.id, 3600 * 24)
		magicLinkTarget.value = user
	} catch (e) {
		console.error('Could not generate magic link', e)
		showError(t('olvid', 'Could not generate magic link'))
	} finally {
		loadingMagicLink.value = null
	}
}
</script>

<style scoped lang="scss">
.users-view {
	&__header {
		display: flex;
		flex-direction: column;
		gap: 8px;
		// leave room for the navigation toggle button on the top-left corner
		padding: 8px 16px 16px calc(var(--default-clickable-area) + 16px);
		border-bottom: 1px solid var(--color-border);
	}

	&__heading {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
	}

	&__title {
		margin: 0;
		font-size: 1.2rem;
		font-weight: bold;
		line-height: var(--default-clickable-area);
	}

	&__intro {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__desc {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	&__revoke-checkbox {
		margin-top: 12px;
	}

	&__revoke-checkbox-desc {
		margin: 4px 0 0;
		padding-inline-start: 28px;
		color: var(--color-text-maxcontrast);
		font-size: 0.875em;
		line-height: 1.4;
	}
}
</style>
