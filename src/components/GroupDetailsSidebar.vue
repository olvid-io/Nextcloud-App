<template>
	<NcAppSidebar
		:name="form.customName || group.displayName"
		:subname="form.customName ? group.displayName : ''"
		@close="$emit('close')">
		<!-- Details tab -->
		<NcAppSidebarTab id="details" :name="t('olvid', 'Details')" :order="0">
			<div class="group-sidebar__fields">
				<NcCheckboxRadioSwitch
					:id="`group-enabled-${group.id}`"
					:checked="group.enabled"
					type="switch"
					:aria-label="t('olvid', 'Enable Olvid for {name}', { name: group.displayName })"
					@update:checked="enableOlvidDiscussionForGroup(group, $event)"
					@click.native.stop>
					{{ t('olvid', 'Olvid Discussion') }}
				</NcCheckboxRadioSwitch>
				<NcTextField
					:value="group.displayName"
					:label="t('olvid', 'Nextcloud name')"
					:disabled="true" />
				<NcTextField
					:value.sync="form.customName"
					:label="t('olvid', 'Custom name')"
					:placeholder="t('olvid', 'Override group name in Olvid')" />
				<div class="group-sidebar__field">
					<label class="group-sidebar__label">{{ t('olvid', 'Description') }}</label>
					<textarea
						v-model="form.description"
						class="group-sidebar__textarea"
						rows="4"
						:placeholder="t('olvid', 'Group description in Olvid')" />
				</div>
				<p v-if="saveError" class="group-sidebar__error">{{ saveError }}</p>
				<p v-if="saveSuccess" class="group-sidebar__success">{{ t('olvid', 'Saved.') }}</p>
				<NcButton :disabled="saving" @click="save">
					{{ t('olvid', 'Save') }}
				</NcButton>
			</div>
		</NcAppSidebarTab>

		<!-- Members tab -->
		<NcAppSidebarTab id="members" :name="t('olvid', 'Members ({n})', { n: members.length })" :order="1">
			<div class="group-sidebar__members">
				<!-- Add member search -->
				<div class="group-sidebar__search">
					<NcTextField
						:value.sync="searchQuery"
						:label="t('olvid', 'Search users to add')"
						:placeholder="t('olvid', 'Type a name…')" />
					<ul v-if="searchResults.length" class="group-sidebar__results">
						<NcListItem
							v-for="user in searchResults"
							:key="user.id"
							:name="user.displayName"
							compact>
							<template #icon>
								<OlvidAvatar :user="user.id" :display-name="user.displayName" :use-olvid="user.useOlvid" />
							</template>
							<template #subname>
								{{ user.id }}
							</template>
							<template #extra-actions>
								<NcButton size="small" @click="addMember(user)">
									{{ t('olvid', 'Add') }}
								</NcButton>
							</template>
						</NcListItem>
					</ul>
				</div>

				<!-- Member list -->
				<ul class="group-sidebar__member-list">
					<NcListItem
						v-for="member in members"
						:key="member.id"
						:name="member.displayName"
						compact>
						<template #icon>
							<OlvidAvatar :user="member.id" :display-name="member.displayName" :use-olvid="member.useOlvid" />
						</template>
						<template #subname>
							{{ member.id }}
						</template>
						<template #extra-actions>
							<NcButton size="small" @click="removeMember(member)">
								{{ t('olvid', 'Remove') }}
							</NcButton>
						</template>
					</NcListItem>
					<li v-if="!members.length" class="group-sidebar__empty">
						{{ t('olvid', 'No members yet.') }}
					</li>
				</ul>
			</div>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import OlvidAvatar from './OlvidAvatar.vue'

export default {
	name: 'GroupDetailsSidebar',
	components: { OlvidAvatar, NcCheckboxRadioSwitch, NcAppSidebar, NcAppSidebarTab, NcTextField, NcButton, NcListItem },

	props: {
		group: {
			type: Object,
			required: true,
		},
	},

	emits: ['close', 'updated'],

	data() {
		return {
			form: {
				customName: this.group.customName ?? '',
				description: this.group.description ?? '',
			},
			members: [...(this.group.members ?? [])],
			saving: false,
			saveError: null,
			saveSuccess: false,
			searchQuery: '',
			searchResults: [],
			searchTimer: null,
		}
	},

	watch: {
		group(newGroup) {
			this.form.customName = newGroup.customName ?? ''
			this.form.description = newGroup.description ?? ''
			this.members = [...(newGroup.members ?? [])]
		},
		searchQuery(val) {
			clearTimeout(this.searchTimer)
			if (!val.trim()) {
				this.searchResults = []
				return
			}
			this.searchTimer = setTimeout(() => this.searchUsers(), 300)
		},
	},

	methods: {
		async save() {
			this.saving = true
			this.saveError = null
			this.saveSuccess = false
			try {
				await axios.put(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(this.group.id)}`), {
					customName: this.form.customName,
					description: this.form.description,
				})
				this.saveSuccess = true
				this.$emit('updated', { id: this.group.id, ...this.form })
			} catch (e) {
				this.saveError = e.response?.data?.error ?? e.message
			} finally {
				this.saving = false
			}
		},

		async enableOlvidDiscussionForGroup(group, enabled) {
			try {
				group.enabled = enabled
				await axios.put(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(this.group.id)}`), { enabled })
			} catch (e) {
				group.enabled = !enabled
				console.error('Could not enable/disable group', e)
			}
		},

		async searchUsers() {
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/users/search'), {
					params: { query: this.searchQuery },
				})
				const memberIds = new Set(this.members.map(m => m.id))
				this.searchResults = (res.data.users ?? []).filter(u => !memberIds.has(u.id))
			} catch (e) {
				this.searchResults = []
			}
		},

		async addMember(user) {
			try {
				await axios.post(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(this.group.id)}/members/${encodeURIComponent(user.id)}`))
				this.members.push(user)
				this.searchResults = this.searchResults.filter(u => u.id !== user.id)
				this.$emit('updated', { id: this.group.id, members: [...this.members] })
			} catch (e) {
				console.error('addMember failed', e)
			}
		},

		async removeMember(member) {
			try {
				await axios.delete(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(this.group.id)}/members/${encodeURIComponent(member.id)}`))
				this.members = this.members.filter(m => m.id !== member.id)
				this.$emit('updated', { id: this.group.id, members: [...this.members] })
			} catch (e) {
				console.error('removeMember failed', e)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.group-sidebar {
	&__fields {
		display: flex;
		flex-direction: column;
		gap: 12px;
		padding: 8px 0;
	}

	&__field {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	&__label {
		font-size: 0.85rem;
		color: var(--color-text-maxcontrast);
	}

	&__textarea {
		width: 100%;
		resize: vertical;
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius);
		padding: 8px;
		background: var(--color-main-background);
		color: var(--color-main-text);
		font-size: inherit;
		font-family: inherit;

		&:focus {
			outline: none;
			border-color: var(--color-primary-element);
		}
	}

	&__error {
		color: var(--color-error);
		margin: 0;
	}

	&__success {
		color: var(--color-success);
		margin: 0;
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

	&__member-list {
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
