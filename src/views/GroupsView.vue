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
			<!-- only show the spinner on first load, later fetches refresh the list in place -->
			<NcLoadingIcon v-if="!groups.loaded" :size="44" />

			<NcEmptyContent
				v-else-if="!groups.list.length"
				:name="t('olvid', 'No groups')"
				:description="t('olvid', 'No Nextcloud groups found.')" />

			<ul v-else class="groups-view__list">
				<NcListItem
					v-for="group in groups.list"
					:key="group.id"
					:name="group.displayName"
					:active="$route.params.groupId === group.id"
					:forceDisplayActions="true"
					@click="openGroup(group)">
					<template #icon>
						<OlvidAvatar
							:displayName="group.displayName"
							:isNoUser="true"
							:useOlvid="group.enabled"
							:url="groups.avatarUrl(group.id)" />
					</template>

					<template #subname>
						{{ n('olvid', '{n} member', '{n} members', group.memberIds.length, { n: group.memberIds.length }) }}
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
							@click.stop="openGroup(group)">
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
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import CreateGroupModal from '../components/CreateGroupModal.vue'
import OlvidAvatar from '../components/OlvidAvatar.vue'
import { useGroupsStore } from '../stores/groups.js'

const groups = useGroupsStore()
const router = useRouter()
const showCreateModal = ref(false)

onMounted(async () => {
	try {
		await groups.fetchAll()
	} catch (e) {
		console.error('Could not load groups', e)
		showError(t('olvid', 'Could not load groups'))
	}
})

function openGroup(group) {
	router.push({ name: 'group-detail', params: { groupId: group.id } })
}

async function enableOlvidDiscussionForGroup(group, enabled) {
	try {
		await groups.update(group.id, { enabled })
	} catch (e) {
		console.error('Could not enable/disable group', e)
		showError(t('olvid', 'Could not update group'))
	}
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
