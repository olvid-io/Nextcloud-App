<template>
	<NcAppContent>
		<div class="users-view__header">
			<span class="users-view__title" />
			<NcButton type="primary" @click="showCreateModal = true">
				{{ t('olvid', '+ Create User') }}
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
					@click="$emit('open-user-sidebar', user)">

					<template #icon>
						<OlvidAvatar :user="user.id" :display-name="user.displayName" :use-olvid="user.useOlvid" />
					</template>

					<template #subname>
						{{ user.id }}
					</template>

					<template #extra-actions>
						<NcButton
							size="small"
							:disabled="loadingMagicLink === user.id"
							@click.stop="openMagicLink(user)">
							{{ t('olvid', 'Magic Link') }}
						</NcButton>
						<NcButton
							size="small"
							type="error"
							@click.stop="confirmDeleteUser(user)">
							{{ t('olvid', 'Delete') }}
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>

		<!-- Magic link modal -->
		<MagicLinkModal
			v-if="magicLinkTarget"
			:user="magicLinkTarget"
			:configuration-url="magicLinkUrl"
			@close="magicLinkTarget = null" />

		<!-- Create user modal -->
		<CreateUserModal
			v-if="showCreateModal"
			@close="showCreateModal = false"
			@created="onUserCreated" />

		<!-- Delete confirmation dialog -->
		<NcDialog
			v-if="deleteTarget"
			:name="t('olvid', 'Delete user')"
			:open="!!deleteTarget"
			@update:open="closeDeleteDialog">
			<p>{{ t('olvid', 'Are you sure you want to delete {name}? This action cannot be undone.', { name: deleteTarget.displayName }) }}</p>
			<NcCheckboxRadioSwitch :checked.sync="deleteRevoke" class="users-view__revoke-checkbox">
				{{ t('olvid', 'This Olvid profile was lost or replaced') }}
			</NcCheckboxRadioSwitch>
			<p class="users-view__revoke-checkbox-desc">
				{{ t('olvid', 'Warning: this action is not reversible. The Olvid identity will be blocked for every other users in this directory. They will no longer be able to reach the user through this identity, and it can never be re-registered. He will need to create a new Olvid profile and re-enroll.') }}
			</p>
			<template #actions>
				<NcButton @click="closeDeleteDialog">{{ t('olvid', 'Cancel') }}</NcButton>
				<NcButton type="error" :disabled="deleting" @click="executeDelete">
					{{ deleting ? t('olvid', 'Deleting…') : (deleteRevoke ? t('olvid', 'Delete and block') : t('olvid', 'Delete')) }}
				</NcButton>
			</template>
		</NcDialog>
	</NcAppContent>
</template>

<script>

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import CreateUserModal from '../components/CreateUserModal.vue'
import MagicLinkModal from '../components/MagicLinkModal.vue'
import OlvidAvatar from '../components/OlvidAvatar.vue'

export default {
	name: 'UsersView',
	components: { NcAppContent, NcButton, NcCheckboxRadioSwitch, NcDialog, NcEmptyContent, NcListItem, NcLoadingIcon, OlvidAvatar, MagicLinkModal, CreateUserModal },

	emits: ['open-user-sidebar'],

	data() {
		return {
			users: [],
			loading: true,
			magicLinkTarget: null,
			magicLinkUrl: null,
			loadingMagicLink: null,
			showCreateModal: false,
			deleteTarget: null,
			deleteRevoke: false,
			deleting: false,
		}
	},

	watch: {
		'$route.params.userId'(id) {
			if (!id) return
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
			} finally {
				this.loading = false
			}
		},

		openUserFromRoute() {
			const id = this.$route.params.userId
			if (!id) return
			const user = this.users.find(u => u.id === id)
			if (user) this.$emit('open-user-sidebar', user)
		},

		async openMagicLink(user) {
			this.loadingMagicLink = user.id
			try {
				const res = await axios.get(generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(user.id)}/magicLink`))
				this.magicLinkUrl = res.data.configurationUrl
				this.magicLinkTarget = user
			} catch (e) {
				console.error('Could not generate magic link', e)
			} finally {
				this.loadingMagicLink = null
			}
		},

		confirmDeleteUser(user) {
			this.deleteTarget = user
		},

		closeDeleteDialog() {
			this.deleteTarget = null
			this.deleteRevoke = false
		},

		async executeDelete() {
			if (!this.deleteTarget) return
			this.deleting = true
			try {
				await axios.delete(generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.deleteTarget.id)}`), { data: { revoke: this.deleteRevoke } })
				this.removeUser(this.deleteTarget.id)
				this.closeDeleteDialog()
			} catch (e) {
				console.error('Could not delete user', e)
			} finally {
				this.deleting = false
			}
		},

		onUserCreated(user) {
			this.users.push(user)
		},

		applyUserPatch(patch) {
			const idx = this.users.findIndex(u => u.id === patch.id)
			if (idx !== -1) {
				this.users.splice(idx, 1, { ...this.users[idx], ...patch })
			}
		},

		removeUser(userId) {
			this.users = this.users.filter(u => u.id !== userId)
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
