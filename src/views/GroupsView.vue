<template>
	<NcAppContent>
		<div class="groups-view__header">
			<span class="groups-view__title" />
			<NcButton variant="primary" @click="showCreateModal = true">
				<template #icon>
					<IconPlus :size="20" />
				</template>
				{{ t('olvid', 'Create Group') }}
			</NcButton>
		</div>

		<div class="groups-view">
			<NcLoadingIcon v-if="loading" :size="44" />

			<NcEmptyContent
				v-else-if="!groups.length"
				:name="t('olvid', 'No groups')"
				:description="t('olvid', 'No Nextcloud groups found.')" />

			<ul v-else class="groups-view__list">
				<NcListItem
					v-for="group in groups"
					:key="group.id"
					:name="group.displayName"
					:active="$route.params.groupId === group.id"
					:forceDisplayActions="true"
					@click="$emit('openGroupSidebar', group)">
					<template #icon>
						<OlvidAvatar
							:displayName="group.displayName"
							:isNoUser="true"
							:useOlvid="group.enabled"
							:url="group.photoUid ? buildGroupAvatarUrl(group) : undefined" />
					</template>

					<template #subname>
						{{ n('olvid', '{n} member', '{n} members', group.members.length, { n: group.members.length }) }}
					</template>

					<template #indicator>
						<NcCheckboxRadioSwitch
							:id="`group-enabled-${group.id}`"
							:modelValue="group.enabled"
							type="switch"
							:aria-label="t('olvid', 'Enable Olvid for {name}', { name: group.displayName })"
							@update:modelValue="enableOlvidDiscussionForGroup(group, $event)"
							@click.stop>
							{{ t('olvid', 'Olvid Discussion') }}
						</NcCheckboxRadioSwitch>
					</template>

					<template #extra-actions>
						<NcButton
							size="small"
							variant="primary"
							@click.stop="$emit('openGroupSidebar', group)">
							<template #icon>
								<IconPencil :size="20" />
							</template>
							{{ t('olvid', 'Details') }}
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>

		<!-- Create group modal -->
		<CreateGroupModal
			v-if="showCreateModal"
			@close="showCreateModal = false"
			@created="onGroupCreated" />
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import CreateGroupModal from '../components/CreateGroupModal.vue'
import OlvidAvatar from '../components/OlvidAvatar.vue'

export default {
	name: 'GroupsView',
	components: { IconPencil, IconPlus, OlvidAvatar, CreateGroupModal, NcListItem, NcButton, NcAppContent, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon },

	emits: ['openGroupSidebar'],

	data() {
		return {
			groups: [],
			loading: true,
			showCreateModal: false,
		}
	},

	watch: {
		'$route.params.groupId': function(id) {
			if (!id) {
				return
			}
			this.openGroupFromRoute()
		},
	},

	async mounted() {
		await this.fetchGroups()
		this.openGroupFromRoute()
	},

	methods: {
		async fetchGroups() {
			this.loading = true
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/groups'))
				this.groups = res.data.groups ?? []
			} catch (e) {
				console.error('Could not load groups', e)
				showError(this.t('olvid', 'Could not load groups'))
			} finally {
				this.loading = false
			}
		},

		openGroupFromRoute() {
			const id = this.$route.params.groupId
			if (!id) {
				return
			}
			const group = this.groups.find((g) => g.id === id)
			if (group) {
				this.$emit('openGroupSidebar', group)
			}
		},

		async enableOlvidDiscussionForGroup(group, enabled) {
			group.enabled = enabled
			try {
				await axios.put(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(group.id)}`), { enabled })
			} catch (e) {
				group.enabled = !enabled
				console.error('Could not enable/disable group', e)
				showError(this.t('olvid', 'Could not update group'))
			}
		},

		applyGroupPatch(patch) {
			const idx = this.groups.findIndex((g) => g.id === patch.id)
			if (idx !== -1) {
				this.groups.splice(idx, 1, { ...this.groups[idx], ...patch })
			}
		},

		onGroupCreated(group) {
			this.groups.push(group)
		},

		buildGroupAvatarUrl(group) {
			return generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(group.id)}/avatar?photoUid=${encodeURIComponent(group.photoUid)}`)
		},
	},
}
</script>

<style scoped lang="scss">
.groups-view {
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
}
</style>
