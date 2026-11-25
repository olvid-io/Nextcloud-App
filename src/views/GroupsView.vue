<template>
	<NcAppContent>
		<div class="groups-view__header">
			<div class="groups-view__heading">
				<h2 class="groups-view__title">
					{{ t('olvid', 'Groups') }}
				</h2>
				<NcButton variant="primary" @click="showCreateModal = true">
					<template #icon>
						<IconPlus :size="20" />
					</template>
					{{ t('olvid', 'Create group') }}
				</NcButton>
			</div>
			<div class="groups-view__intro">
				<p class="groups-view__desc">
					{{ t('olvid', 'Turn on the Olvid discussion of a group to create a secure group discussion in Olvid with its members. Groups with the discussion turned off are not visible in Olvid.') }}
				</p>
				<p class="groups-view__desc">
					{{ t('olvid', 'The discussion follows the group automatically: members added to or removed from the group in Nextcloud are also added to or removed from the discussion. Members who have not linked their Olvid profile yet join the discussion as soon as they do. Open a group to change the name, description and photo of its discussion.') }}
				</p>
			</div>
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
						{{ membersSummary(group) }}
					</template>

					<template #indicator>
						<NcCheckboxRadioSwitch
							:id="`group-enabled-${group.id}`"
							:modelValue="group.enabled"
							type="switch"
							:aria-label="t('olvid', 'Create an Olvid discussion for this group')"
							@update:modelValue="enableOlvidDiscussionForGroup(group, $event)"
							@click.stop>
							{{ t('olvid', 'Olvid discussion') }}
						</NcCheckboxRadioSwitch>
					</template>

					<template #extra-actions>
						<NcButton
							size="small"
							variant="tertiary"
							:aria-label="t('olvid', 'Details')"
							:title="t('olvid', 'Details')"
							@click.stop="openGroup(group)">
							<template #icon>
								<IconPencil :size="20" />
							</template>
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
import { n, t } from '@nextcloud/l10n'
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

function membersSummary(group) {
	const total = group.memberIds?.length ?? 0
	if (!total) {
		return t('olvid', 'No members')
	}
	const olvidCount = groups.membersOf(group.id).filter((user) => user.useOlvid).length
	return n('olvid', '{olvidCount} of {n} member uses Olvid', '{olvidCount} of {n} members use Olvid', total, { olvidCount, n: total })
}

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
}
</style>
