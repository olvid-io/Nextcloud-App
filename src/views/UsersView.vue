<template>
	<NcAppContent>
		<div class="users-view__header">
			<span class="users-view__title" />
			<NcButton variant="primary" @click="showCreateModal = true">
				<template #icon>
					<IconPlus :size="20" />
				</template>
				{{ t('olvid', 'Create User') }}
			</NcButton>
		</div>

		<div class="users-view">
			<NcLoadingIcon v-if="loading" :size="44" />

			<NcEmptyContent
				v-else-if="!users.length"
				:name="t('olvid', 'No users')"
				:description="t('olvid', 'No Nextcloud users found.')" />

			<ul v-else class="users-view__list">
				<NcListItem
					v-for="user in users"
					:key="user.id"
					:name="user.displayName"
					:active="$route.params.userId === user.id"
					@click="$emit('openUserSidebar', user)">
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
							{{ t('olvid', 'Magic Link') }}
						</NcButton>
						<NcButton
							size="small"
							variant="primary"
							@click="$emit('openUserSidebar', user)">
							<template #icon>
								<IconPencil :size="20" />
							</template>
							{{ t('olvid', 'Details') }}
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
			@close="showCreateModal = false"
			@created="onUserCreated" />
	</NcAppContent>
</template>

<script>

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
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

export default {
	name: 'UsersView',
	components: { NcAppContent, NcButton, NcEmptyContent, NcListItem, NcLoadingIcon, OlvidAvatar, MagicLinkModal, CreateUserModal, IconPlus, IconPencil },

	emits: ['openUserSidebar'],

	data() {
		return {
			users: [],
			loading: true,
			magicLinkTarget: null,
			magicLinkUrl: null,
			loadingMagicLink: null,
			showCreateModal: false,
		}
	},

	watch: {
		'$route.params.userId': function(id) {
			if (!id) {
				return
			}
			this.openUserFromRoute()
		},
	},

	async mounted() {
		await this.fetchUsers()
		this.openUserFromRoute()
	},

	methods: {
		async fetchUsers() {
			this.loading = true
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/users'))
				this.users = res.data.users ?? []
			} catch (e) {
				console.error('Could not load users', e)
				showError(this.t('olvid', 'Could not load users'))
			} finally {
				this.loading = false
			}
		},

		openUserFromRoute() {
			const id = this.$route.params.userId
			if (!id) {
				return
			}
			const user = this.users.find((u) => u.id === id)
			if (user) {
				this.$emit('openUserSidebar', user)
			}
		},

		async openMagicLink(user) {
			this.loadingMagicLink = user.id
			try {
				const res = await axios.get(generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(user.id)}/magicLink`))
				this.magicLinkUrl = res.data.configurationUrl
				this.magicLinkTarget = user
			} catch (e) {
				console.error('Could not generate magic link', e)
				showError(this.t('olvid', 'Could not generate magic link'))
			} finally {
				this.loadingMagicLink = null
			}
		},

		onUserCreated(user) {
			this.users.push(user)
		},

		applyUserPatch(patch) {
			const idx = this.users.findIndex((u) => u.id === patch.id)
			if (idx !== -1) {
				this.users.splice(idx, 1, { ...this.users[idx], ...patch })
			}
		},

		removeUser(userId) {
			this.users = this.users.filter((u) => u.id !== userId)
		},
	},
}
</script>

<style scoped lang="scss">
.users-view {
	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 16px 16px 8px;
		border-bottom: 1px solid var(--color-border);
	}

	&__title {
		margin: 0;
		font-size: 1.1rem;
		font-weight: 600;
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
