<template>
	<NcAppContent>
		<NcHeaderMenu id="groups-header" />
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
					:force-display-actions="true"
					@click="$emit('open-group-sidebar', group)">

					<template #icon>
						<NcAvatar :display-name="group.displayName" :is-no-user="true" />
					</template>

					<template #subname>
						{{ n('olvid', '{n} member', '{n} members', group.members.length, { n: group.members.length }) }}
					</template>

					<template #indicator>
						<NcCheckboxRadioSwitch
							:id="`group-enabled-${group.id}`"
							:checked="group.enabled"
							type="switch"
							:aria-label="t('olvid', 'Enable Olvid for {name}', { name: group.displayName })"
							@update:checked="enableOlvidDiscussionForGroup(group, $event)"
							@click.native.stop>
							{{ t('olvid', 'Olvid Discussion') }}
						</NcCheckboxRadioSwitch>
					</template>

					<template #extra-actions>
						<NcButton size="small" @click.stop="$emit('open-group-sidebar', group)">
							{{ t('olvid', 'Details') }}
						</NcButton>
					</template>
				</NcListItem>
			</ul>
		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcHeaderMenu from '@nextcloud/vue/dist/Components/NcHeaderMenu.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

export default {
	name: 'GroupsView',
	components: { NcListItem, NcAvatar, NcButton, NcHeaderMenu, NcAppContent, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon },

	emits: ['open-group-sidebar'],

	data() {
		return {
			groups: [],
			loading: true,
		}
	},

	watch: {
		'$route.params.groupId'(id) {
			if (!id) return
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
			} finally {
				this.loading = false
			}
		},

		openGroupFromRoute() {
			const id = this.$route.params.groupId
			if (!id) return
			const group = this.groups.find(g => g.id === id)
			if (group) this.$emit('open-group-sidebar', group)
		},

		async enableOlvidDiscussionForGroup(group, enabled) {
			group.enabled = enabled
			try {
				await axios.put(generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(group.id)}`), { enabled })
			} catch (e) {
				group.enabled = !enabled
				console.error('Could not enable/disable group', e)
			}
		},

		applyGroupPatch(patch) {
			const idx = this.groups.findIndex(g => g.id === patch.id)
			if (idx !== -1) {
				this.groups.splice(idx, 1, { ...this.groups[idx], ...patch })
			}
		},
	},
}
</script>
