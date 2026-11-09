<template>
	<NcAppSidebar
		v-if="group"
		:name="group.displayName"
		:subname="group.id !== group.displayName ? group.id : ''"
		@close="close">
		<!-- Details tab -->
		<NcAppSidebarTab id="details" :name="t('olvid', 'Details')" :order="0">
			<template #icon>
				<IconCardAccountDetails :size="20" />
			</template>

			<div class="group-sidebar__fields">
				<!-- Group avatar picker: uploads immediately on selection -->
				<div class="group-sidebar__avatar">
					<AvatarPicker
						:currentUrl="groups.avatarUrl(group.id)"
						:displayName="group.displayName"
						:size="192"
						@change="handleAvatarChange" />
					<p v-if="avatarUploading" class="group-sidebar__hint">
						{{ t('olvid', 'Update in progress…') }}
					</p>
					<p v-if="avatarError" class="group-sidebar__error">
						{{ avatarError }}
					</p>
					<h3 class="group-sidebar__discussion-name">
						{{ form.discussionName }}
					</h3>
				</div>

				<NcCheckboxRadioSwitch
					:id="`group-enabled-${group.id}`"
					:modelValue="group.enabled"
					type="switch"
					:aria-label="t('olvid', 'Create an Olvid discussion for this group')"
					@update:modelValue="enableOlvidDiscussion"
					@click.stop>
					{{ t('olvid', 'Olvid Discussion') }}
				</NcCheckboxRadioSwitch>
				<NcTextField
					v-model="form.discussionName"
					:label="t('olvid', 'Olvid discussion name')"
					:placeholder="t('olvid', 'Set Olvid discussion name')" />
				<div class="group-sidebar__field">
					<label class="group-sidebar__label">{{ t('olvid', 'Olvid discussion description') }}</label>
					<textarea
						v-model="form.discussionDescription"
						class="group-sidebar__textarea"
						rows="4"
						:placeholder="t('olvid', 'Olvid discussion description')" />
				</div>
				<p v-if="saveError" class="group-sidebar__error">
					{{ saveError }}
				</p>
				<p v-if="saveSuccess" class="group-sidebar__success">
					{{ t('olvid', 'Saved.') }}
				</p>
				<NcButton :disabled="saving || !form.discussionName" @click="save">
					{{ t('olvid', 'Save') }}
				</NcButton>
			</div>
		</NcAppSidebarTab>

		<!-- Members tab -->
		<NcAppSidebarTab id="members" :name="t('olvid', 'Members ({n})', { n: members.length })" :order="1">
			<template #icon>
				<IconAccountGroup :size="20" />
			</template>

			<div class="group-sidebar__members">
				<!-- Add member search -->
				<div class="group-sidebar__search">
					<NcTextField
						v-model="searchQuery"
						:label="t('olvid', 'Search users to add')"
						:placeholder="t('olvid', 'Type a name…')" />
					<ul v-if="searchResults.length" class="group-sidebar__results">
						<NcListItem
							v-for="user in searchResults"
							:key="user.id"
							:name="user.displayName"
							compact>
							<template #icon>
								<OlvidAvatar :user="user.id" :displayName="user.displayName" :useOlvid="user.useOlvid" />
							</template>
							<template v-if="user.id !== user.displayName" #subname>
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
							<OlvidAvatar :user="member.id" :displayName="member.displayName" :useOlvid="member.useOlvid" />
						</template>
						<template v-if="member.id !== member.displayName" #subname>
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

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconAccountGroup from 'vue-material-design-icons/AccountGroupOutline.vue'
import IconCardAccountDetails from 'vue-material-design-icons/CardAccountDetails.vue'
import AvatarPicker from './AvatarPicker.vue'
import OlvidAvatar from './OlvidAvatar.vue'
import { useGroupsStore } from '../stores/groups.js'
import { useUsersStore } from '../stores/users.js'

const props = defineProps({
	groupId: {
		type: String,
		required: true,
	},
})

const groups = useGroupsStore()
const users = useUsersStore()
const router = useRouter()

const group = computed(() => groups.byId[props.groupId])
const members = computed(() => groups.membersOf(props.groupId))

// Local draft of the editable fields, committed on Save
const form = reactive({ discussionName: '', discussionDescription: '' })
const saving = ref(false)
const saveError = ref(null)
const saveSuccess = ref(false)

// Avatar upload state (separate from the main Save flow)
const avatarUploading = ref(false)
const avatarError = ref(null)

const searchQuery = ref('')
const searchResultIds = ref([])
let searchTimer = null
const searchResults = computed(() => {
	const memberIds = new Set(group.value?.memberIds ?? [])
	return searchResultIds.value.filter((id) => !memberIds.has(id)).map((id) => users.byId[id]).filter(Boolean)
})

// Refill the draft when another group is shown or when the stored values change
// (e.g. groups list arriving after a direct load of /groups/:groupId)
watch(() => [props.groupId, group.value?.discussionName, group.value?.discussionDescription], () => {
	form.discussionName = group.value?.discussionName ?? ''
	form.discussionDescription = group.value?.discussionDescription ?? ''
}, { immediate: true })

watch(() => props.groupId, () => {
	saveError.value = null
	saveSuccess.value = false
	avatarError.value = null
	searchQuery.value = ''
})

// Unknown group id once the list is loaded: back to the list
watch(() => groups.loaded && !group.value, (notFound) => {
	if (notFound) {
		router.replace({ name: 'groups' })
	}
}, { immediate: true })

watch(searchQuery, (val) => {
	clearTimeout(searchTimer)
	if (!val.trim()) {
		searchResultIds.value = []
		return
	}
	searchTimer = setTimeout(searchUsers, 300)
})

function close() {
	router.push({ name: 'groups' })
}

/**
 * Called by AvatarPicker when the user selects a new image (or removes it).
 * Uploads immediately — no need to click the Save button for the avatar.
 *
 * @param {string|null} dataUrl JPEG data URL, null to remove the photo
 */
async function handleAvatarChange(dataUrl) {
	avatarUploading.value = true
	avatarError.value = null
	try {
		await groups.uploadAvatar(props.groupId, dataUrl)
	} catch (e) {
		avatarError.value = e.response?.data?.error ?? e.message
	} finally {
		avatarUploading.value = false
	}
}

async function save() {
	saving.value = true
	saveError.value = null
	saveSuccess.value = false
	try {
		await groups.update(props.groupId, { ...form })
		saveSuccess.value = true
	} catch (e) {
		saveError.value = e.response?.data?.error ?? e.message
	} finally {
		saving.value = false
	}
}

async function enableOlvidDiscussion(enabled) {
	try {
		await groups.update(props.groupId, { enabled })
	} catch (e) {
		console.error('Could not enable/disable group', e)
		showError(t('olvid', 'Could not update group'))
	}
}

async function searchUsers() {
	try {
		searchResultIds.value = await users.search(searchQuery.value)
	} catch {
		searchResultIds.value = []
	}
}

async function addMember(user) {
	try {
		await groups.addMember(props.groupId, user.id)
	} catch (e) {
		console.error('addMember failed', e)
		showError(t('olvid', 'Could not update group'))
	}
}

async function removeMember(member) {
	try {
		await groups.removeMember(props.groupId, member.id)
	} catch (e) {
		console.error('removeMember failed', e)
		showError(t('olvid', 'Could not update group'))
	}
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

	&__avatar {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 4px;
		padding-bottom: 4px;
		border-bottom: 1px solid var(--color-border);
		margin-bottom: 4px;
	}

	&__discussion-name {
		margin: 0;
	}

	&__hint {
		color: var(--color-text-maxcontrast);
		font-size: 0.85rem;
		margin: 0;
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
