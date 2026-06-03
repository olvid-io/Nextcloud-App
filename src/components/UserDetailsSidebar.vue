<template>
	<NcAppSidebar
		:name="user.displayName"
		:subname="user.id"
		@close="$emit('close')">

		<!-- Profile tab -->
		<NcAppSidebarTab id="profile" :name="t('olvid', 'Profile')" :order="0">
			<div class="user-sidebar__fields">
				<NcTextField
					:value="user.displayName"
					:label="t('olvid', 'Nextcloud display name')"
					:disabled="true" />
				<NcTextField
					:value="user.id"
					:label="t('olvid', 'User ID')"
					:disabled="true" />

				<hr class="user-sidebar__separator" />

				<NcTextField
					:value.sync="form.firstname"
					:label="t('olvid', 'First name')" />
				<NcTextField
					:value.sync="form.lastname"
					:label="t('olvid', 'Last name')" />
				<NcTextField
					:value.sync="form.position"
					:label="t('olvid', 'Position')" />
				<NcTextField
					:value.sync="form.company"
					:label="t('olvid', 'Company')" />

				<p v-if="saveError" class="user-sidebar__error">{{ saveError }}</p>
				<p v-if="saveSuccess" class="user-sidebar__success">{{ t('olvid', 'Saved.') }}</p>

				<NcButton :disabled="saving" @click="save">
					{{ t('olvid', 'Save') }}
				</NcButton>

				<div class="user-sidebar__danger">
					<NcButton type="error" @click="showDeleteConfirm = true">
						{{ t('olvid', 'Delete user') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>

		<!-- Groups tab -->
		<NcAppSidebarTab id="groups" :name="t('olvid', 'Groups ({n})', { n: groups.length })" :order="1">
			<div class="user-sidebar__members">
				<!-- Add to group search -->
				<div class="user-sidebar__search">
					<NcTextField
						:value.sync="groupSearchQuery"
						:label="t('olvid', 'Add to group')"
						:placeholder="t('olvid', 'Search groups…')" />
					<ul v-if="groupSearchResults.length" class="user-sidebar__results">
						<NcListItem
							v-for="group in groupSearchResults"
							:key="group.id"
							:name="group.displayName"
							compact>
							<template #subname>
								{{ group.id }}
							</template>
							<template #extra-actions>
								<NcButton size="small" @click="addToGroup(group)">
									{{ t('olvid', 'Add') }}
								</NcButton>
							</template>
						</NcListItem>
					</ul>
				</div>

				<!-- Current groups -->
				<NcLoadingIcon v-if="loadingGroups" :size="32" />
				<ul v-else class="user-sidebar__group-list">
					<NcListItem
						v-for="group in groups"
						:key="group.id"
						:name="group.displayName"
						compact>
						<template #subname>
							{{ group.id }}
						</template>
						<template #extra-actions>
							<NcButton size="small" @click="removeFromGroup(group)">
								{{ t('olvid', 'Remove') }}
							</NcButton>
						</template>
					</NcListItem>
					<li v-if="!groups.length" class="user-sidebar__empty">
						{{ t('olvid', 'Not in any group.') }}
					</li>
				</ul>
			</div>
		</NcAppSidebarTab>

		<!-- Delete confirmation dialog -->
		<NcDialog
			v-if="showDeleteConfirm"
			:name="t('olvid', 'Delete user')"
			:open="showDeleteConfirm"
			@update:open="showDeleteConfirm = $event">
			<p>{{ t('olvid', 'Are you sure you want to delete {name}? This action cannot be undone.', { name: user.displayName }) }}</p>
			<template #actions>
				<NcButton @click="showDeleteConfirm = false">{{ t('olvid', 'Cancel') }}</NcButton>
				<NcButton type="error" :disabled="deleting" @click="deleteUser">
					{{ deleting ? t('olvid', 'Deleting…') : t('olvid', 'Delete') }}
				</NcButton>
			</template>
		</NcDialog>
	</NcAppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import OlvidAvatar from './OlvidAvatar.vue'

export default {
	name: 'UserDetailsSidebar',
	components: { NcAppSidebar, NcAppSidebarTab, NcTextField, NcButton, NcListItem, NcLoadingIcon, NcDialog, OlvidAvatar },

	props: {
		user: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'updated', 'deleted'],

	data() {
		return {
			form: {
				firstname: this.user.firstname ?? '',
				lastname: this.user.lastname ?? '',
				position: this.user.position ?? '',
				company: this.user.company ?? '',
			},
			saving: false,
			saveError: null,
			saveSuccess: false,
			groups: [],
			loadingGroups: true,
			groupSearchQuery: '',
			groupSearchResults: [],
			groupSearchTimer: null,
			allGroups: [],
			showDeleteConfirm: false,
			deleting: false,
		}
	},

	watch: {
		user(newUser) {
			this.form.firstname = newUser.firstname ?? ''
			this.form.lastname = newUser.lastname ?? ''
			this.form.position = newUser.position ?? ''
			this.form.company = newUser.company ?? ''
			this.loadGroups()
		},
		groupSearchQuery(val) {
			clearTimeout(this.groupSearchTimer)
			if (!val.trim()) {
				this.groupSearchResults = []
				return
			}
			this.groupSearchTimer = setTimeout(() => this.searchGroups(), 300)
		},
	},

	async mounted() {
		await Promise.all([this.loadGroups(), this.loadAllGroups()])
	},

	methods: {
		async save() {
			this.saving = true
			this.saveError = null
			this.saveSuccess = false
			try {
				await axios.put(
					generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.user.id)}`),
					this.form,
				)
				this.saveSuccess = true
				this.$emit('updated', { id: this.user.id, ...this.form })
			} catch (e) {
				this.saveError = e.response?.data?.error ?? e.message
			} finally {
				this.saving = false
			}
		},

		async loadGroups() {
			this.loadingGroups = true
			try {
				const res = await axios.get(generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.user.id)}/groups`))
				this.groups = res.data.groups ?? []
			} catch (e) {
				console.error('Could not load user groups', e)
			} finally {
				this.loadingGroups = false
			}
		},

		async loadAllGroups() {
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/groups'))
				this.allGroups = res.data.groups ?? []
			} catch (e) {
				console.error('Could not load groups list', e)
			}
		},

		searchGroups() {
			const q = this.groupSearchQuery.toLowerCase()
			const currentIds = new Set(this.groups.map(g => g.id))
			this.groupSearchResults = this.allGroups.filter(g =>
				!currentIds.has(g.id)
				&& (g.displayName.toLowerCase().includes(q) || g.id.toLowerCase().includes(q)),
			)
		},

		async addToGroup(group) {
			try {
				await axios.post(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(group.id)}/members/${encodeURIComponent(this.user.id)}`))
				this.groups.push(group)
				this.groupSearchResults = this.groupSearchResults.filter(g => g.id !== group.id)
			} catch (e) {
				console.error('addToGroup failed', e)
			}
		},

		async removeFromGroup(group) {
			try {
				await axios.delete(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(group.id)}/members/${encodeURIComponent(this.user.id)}`))
				this.groups = this.groups.filter(g => g.id !== group.id)
			} catch (e) {
				console.error('removeFromGroup failed', e)
			}
		},

		async deleteUser() {
			this.deleting = true
			try {
				await axios.delete(generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(this.user.id)}`))
				this.$emit('deleted', this.user.id)
			} catch (e) {
				console.error('deleteUser failed', e)
				this.showDeleteConfirm = false
			} finally {
				this.deleting = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
.user-sidebar {
	&__fields {
		display: flex;
		flex-direction: column;
		gap: 12px;
		padding: 8px 0;
	}

	&__info {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__separator {
		margin: 4px 0;
		border: none;
		border-top: 1px solid var(--color-border);
	}

	&__error {
		color: var(--color-error);
		margin: 0;
	}

	&__success {
		color: var(--color-success);
		margin: 0;
	}

	&__danger {
		margin-top: 16px;
		padding-top: 16px;
		border-top: 1px solid var(--color-border);
	}

	&__members {
		display: flex;
		flex-direction: column;
		gap: 12px;
		padding: 8px 0;
	}

	&__search {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__results {
		list-style: none;
		padding: 0;
		margin: 0;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius);
		max-height: 180px;
		overflow-y: auto;
	}

	&__group-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	&__empty {
		color: var(--color-text-maxcontrast);
		padding: 8px 0;
		font-style: italic;
	}
}
</style>
