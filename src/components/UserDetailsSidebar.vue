<template>
	<NcAppSidebar
		v-if="user"
		:name="user.displayName"
		:subname="user.id !== user.displayName ? user.id : ''"
		@close="close">
		<!-- Profile tab -->
		<NcAppSidebarTab id="profile" :name="t('olvid', 'Olvid Profile')" :order="0">
			<template #icon>
				<IconCardAccountDetails :size="20" />
			</template>

			<div class="user-sidebar__fields">
				<div style="display: flex; justify-content: center;">
					<OlvidAvatar :user="user.id"
						:displayName="user.displayName"
						:useOlvid="user.useOlvid"
						:isInactive="user.isInactive"
						:size="192" />
				</div>

				<h3 style="margin: 0; display: flex; justify-content: center;">
					{{ olvidDisplayNameFromForm }}
				</h3>

				<hr class="user-sidebar__separator">

				<div>{{ t('olvid', 'Olvid Profile') }}</div>
				<NcTextField
					v-model="form.firstname"
					:label="t('olvid', 'First name')" />
				<NcTextField
					v-model="form.lastname"
					:label="t('olvid', 'Last name')" />
				<NcTextField
					v-model="form.position"
					:label="t('olvid', 'Position')" />
				<NcTextField
					v-model="form.company"
					:label="t('olvid', 'Company')" />

				<p v-if="saveError" class="user-sidebar__error">
					{{ saveError }}
				</p>
				<p v-if="saveSuccess" class="user-sidebar__success">
					{{ t('olvid', 'Saved.') }}
				</p>

				<NcButton :disabled="saving || (!form.firstname.trim() && !form.lastname.trim())" @click="save">
					{{ t('olvid', 'Save') }}
				</NcButton>

				<div class="user-sidebar__actions">
					<NcButton v-if="!user.useOlvid"
						class="user-sidebar__button"
						:disabled="loadingMagicLink === user.id"
						@click.stop="openMagicLink">
						{{ t('olvid', 'Magic Link') }}
					</NcButton>
				</div>

				<div class="user-sidebar__danger">
					<NcButton v-if="user.useOlvid"
						class="user-sidebar__button"
						variant="error"
						@click="showUnlinkConfirm = true">
						{{ t('olvid', 'Revoke user identity') }}
					</NcButton>

					<NcButton variant="error" class="user-sidebar__button" @click="showDeleteConfirm = true">
						{{ t('olvid', 'Delete user') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>

		<!-- Groups tab -->
		<NcAppSidebarTab id="groups" :name="t('olvid', 'Groups ({n})', { n: userGroups.length })" :order="1">
			<template #icon>
				<IconAccountGroup :size="20" />
			</template>

			<div class="user-sidebar__members">
				<!-- Add to group search -->
				<div class="user-sidebar__search">
					<NcTextField
						v-model="groupSearchQuery"
						:label="t('olvid', 'Add to group')"
						:placeholder="t('olvid', 'Search groups…')" />
					<ul v-if="groupSearchResults.length" class="user-sidebar__results">
						<NcListItem
							v-for="group in groupSearchResults"
							:key="group.id"
							:name="group.displayName"
							compact>
							<template #icon>
								<OlvidAvatar
									:displayName="group.displayName"
									:isNoUser="true"
									:useOlvid="group.enabled"
									:url="groups.avatarUrl(group.id)" />
							</template>
							<template v-if="group.id !== group.displayName" #subname>
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
				<NcLoadingIcon v-if="!groups.loaded" :size="32" />
				<ul v-else class="user-sidebar__group-list">
					<NcListItem
						v-for="group in userGroups"
						:key="group.id"
						:name="group.displayName">
						<template #icon>
							<OlvidAvatar
								:displayName="group.displayName"
								:isNoUser="true"
								:useOlvid="group.enabled"
								:url="groups.avatarUrl(group.id)" />
						</template>
						<template v-if="group.id !== group.displayName" #subname>
							{{ group.id }}
						</template>
						<template #extra-actions>
							<NcButton size="small" @click="removeFromGroup(group)">
								{{ t('olvid', 'Remove') }}
							</NcButton>
						</template>
					</NcListItem>
					<li v-if="!userGroups.length" class="user-sidebar__empty">
						{{ user.displayName }} {{ t('olvid', 'is not in any group.') }}
					</li>
				</ul>
			</div>
		</NcAppSidebarTab>

		<!-- Magic link modal -->
		<MagicLinkModal
			v-if="magicLinkTarget"
			:user="magicLinkTarget"
			:configurationUrl="magicLinkUrl"
			@close="magicLinkTarget = null" />

		<!-- Unlink/revoke identity modal -->
		<UnlinkIdentityModal
			v-if="showUnlinkConfirm"
			:user="user"
			@close="showUnlinkConfirm = false"
			@unlinked="showUnlinkConfirm = false" />

		<!-- Delete confirmation modal -->
		<DeleteUserModal
			v-if="showDeleteConfirm"
			:user="user"
			@close="showDeleteConfirm = false"
			@deleted="onUserDeleted" />
	</NcAppSidebar>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconAccountGroup from 'vue-material-design-icons/AccountGroupOutline.vue'
import IconCardAccountDetails from 'vue-material-design-icons/CardAccountDetails.vue'
import DeleteUserModal from './DeleteUserModal.vue'
import MagicLinkModal from './MagicLinkModal.vue'
import OlvidAvatar from './OlvidAvatar.vue'
import UnlinkIdentityModal from './UnlinkIdentityModal.vue'
import { useGroupsStore } from '../stores/groups.js'
import { useUsersStore } from '../stores/users.js'

const props = defineProps({
	userId: {
		type: String,
		required: true,
	},
})

const users = useUsersStore()
const groups = useGroupsStore()
const router = useRouter()

const user = computed(() => users.byId[props.userId])
// derived from the groups store member lists (all groups are loaded on mount)
const userGroups = computed(() => groups.groupsOfUser(props.userId))

// Local draft of the Olvid profile fields, committed on Save
const form = reactive({ firstname: '', lastname: '', position: '', company: '' })
const saving = ref(false)
const saveError = ref(null)
const saveSuccess = ref(false)

const groupSearchQuery = ref('')
const groupSearchResults = ref([])
let groupSearchTimer = null

const showDeleteConfirm = ref(false)
const showUnlinkConfirm = ref(false)
const magicLinkTarget = ref(null)
const magicLinkUrl = ref(null)
const loadingMagicLink = ref(null)

const olvidDisplayNameFromForm = computed(() => {
	const name = `${form.firstname} ${form.lastname}`.trim()
	const role = [form.position, form.company].filter(Boolean).join(' @ ')
	return role ? `${name} (${role})` : name
})

// Refill the draft when another user is shown or when the stored values change
// (e.g. users list arriving after a direct load of /users/:userId)
watch(() => [props.userId, user.value?.firstname, user.value?.lastname, user.value?.position, user.value?.company], () => {
	form.firstname = user.value?.firstname ?? ''
	form.lastname = user.value?.lastname ?? ''
	form.position = user.value?.position ?? ''
	form.company = user.value?.company ?? ''
}, { immediate: true })

watch(() => props.userId, () => {
	saveError.value = null
	saveSuccess.value = false
	groupSearchQuery.value = ''
})

// Unknown user id once the list is loaded: back to the list
watch(() => users.loaded && !user.value, (notFound) => {
	if (notFound) {
		router.replace({ name: 'users' })
	}
}, { immediate: true })

watch(groupSearchQuery, (val) => {
	clearTimeout(groupSearchTimer)
	if (!val.trim()) {
		groupSearchResults.value = []
		return
	}
	groupSearchTimer = setTimeout(searchGroups, 300)
})

onMounted(async () => {
	try {
		await groups.fetchAll()
	} catch (e) {
		console.error('Could not load groups list', e)
		showError(t('olvid', 'Could not load groups'))
	}
})

function close() {
	router.push({ name: 'users' })
}

async function save() {
	saving.value = true
	saveError.value = null
	saveSuccess.value = false
	try {
		await users.update(props.userId, { ...form })
		saveSuccess.value = true
	} catch (e) {
		saveError.value = e.response?.data?.error ?? e.message
	} finally {
		saving.value = false
	}
}

function searchGroups() {
	const q = groupSearchQuery.value.toLowerCase()
	const currentIds = new Set(userGroups.value.map((g) => g.id))
	groupSearchResults.value = groups.list.filter((g) => !currentIds.has(g.id)
		&& (g.displayName.toLowerCase().includes(q) || g.id.toLowerCase().includes(q)))
}

async function addToGroup(group) {
	try {
		await groups.addMember(group.id, props.userId)
		groupSearchResults.value = groupSearchResults.value.filter((g) => g.id !== group.id)
	} catch (e) {
		console.error('addToGroup failed', e)
		showError(t('olvid', 'Could not update group'))
	}
}

async function removeFromGroup(group) {
	try {
		await groups.removeMember(group.id, props.userId)
	} catch (e) {
		console.error('removeFromGroup failed', e)
		showError(t('olvid', 'Could not update group'))
	}
}

function onUserDeleted() {
	showDeleteConfirm.value = false
	close()
}

async function openMagicLink() {
	loadingMagicLink.value = props.userId
	try {
		magicLinkUrl.value = await users.getMagicLink(props.userId)
		magicLinkTarget.value = user.value
	} catch (e) {
		console.error('Could not generate magic link', e)
		showError(t('olvid', 'Could not generate magic link'))
	} finally {
		loadingMagicLink.value = null
	}
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

	&__danger, &__actions {
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

	&__button {
		margin: 5px;
	}
}
</style>
