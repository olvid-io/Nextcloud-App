import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useUsersStore } from './users.js'

function groupUrl(groupId) {
	return generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(groupId)}`)
}
function groupAvatarUploadUrl(groupUid) {
	return generateOcsUrl(`/apps/olvid/app/groups/avatar/${groupUid}`)
}
function groupAvatarUrl(photoUid) {
	return generateOcsUrl(`/apps/olvid/app/groups/avatar?photoUid=${encodeURIComponent(photoUid)}`)
}

/**
 * Single source of truth for groups. Members are normalized: a group only stores
 * `memberIds`, the member users themselves live in the users store.
 */
export const useGroupsStore = defineStore('groups', () => {
	/** All known groups, by id: { id, displayName, enabled, photoUid, discussionName, discussionDescription, memberIds? } */
	const byId = ref({})
	/** Ids returned by GET /app/groups, in server order. */
	const ids = ref([])
	/**
	 * Client-side avatar revision, by group id. Bumped on every avatar upload and appended
	 * to the avatar URL so the browser fetches the new image. Never sent by the server.
	 */
	const loading = ref(false)
	const loaded = ref(false)

	const list = computed(() => ids.value.map((id) => byId.value[id]).filter(Boolean))

	/**
	 * Merge a group payload into the store, keeping object identity. Embedded `members`
	 * are moved to the users store; payloads without `members` leave `memberIds` untouched.
	 *
	 * @param {object} payload group payload, must have an id
	 * @return {object} the stored (reactive) group
	 */
	function upsert(payload) {
		const { members, ...group } = payload
		if (members) {
			const users = useUsersStore()
			group.memberIds = members.map((member) => users.upsert(member).id)
		}
		const existing = byId.value[group.id]
		if (existing) {
			Object.assign(existing, group)
		} else {
			byId.value[group.id] = group
		}
		return byId.value[group.id]
	}

	/**
	 * @param {string} groupId group id
	 * @return {string|null} avatar URL, or null when the group has no photo
	 */
	function avatarUrl(groupId) {
		if (byId.value[groupId]?.photoUid === false) {
			return null
		}
		return `${groupAvatarUrl(byId.value[groupId].photoUid)}`
	}

	function membersOf(groupId) {
		const users = useUsersStore()
		return (byId.value[groupId]?.memberIds ?? []).map((userId) => users.byId[userId]).filter(Boolean)
	}

	// Groups (among those with a known member list) the user belongs to
	function groupsOfUser(userId) {
		return list.value.filter((group) => group.memberIds?.includes(userId))
	}

	async function fetchAll() {
		loading.value = true
		try {
			const res = await axios.get(generateOcsUrl('/apps/olvid/app/groups'))
			ids.value = (res.data.groups ?? []).map((group) => upsert(group).id)
			loaded.value = true
		} finally {
			loading.value = false
		}
	}

	async function create(groupId) {
		const res = await axios.post(generateOcsUrl('/apps/olvid/app/groups'), { id: groupId })
		const group = upsert(res.data)
		ids.value.push(group.id)
		return group
	}

	/**
	 * Optimistically apply the patch, roll it back if the request fails.
	 *
	 * @param {string} groupId group id
	 * @param {object} patch any of enabled, discussionName, discussionDescription
	 */
	async function update(groupId, patch) {
		const group = byId.value[groupId]
		const previous = Object.fromEntries(Object.keys(patch).map((key) => [key, group[key]]))
		Object.assign(group, patch)
		try {
			await axios.put(groupUrl(groupId), patch)
		} catch (e) {
			Object.assign(group, previous)
			throw e
		}
	}

	/**
	 * @param {string} groupId group id
	 * @param {string|null} dataUrl JPEG data URL, or null to remove the photo
	 */
	async function uploadAvatar(groupId, dataUrl) {
		const res = await axios.put(groupAvatarUploadUrl(groupId), { photoData: dataUrl })
		byId.value[groupId].photoUid = res.data.photoUid
	}

	// Local-only membership update (the request was already made)
	function linkMember(groupId, userId) {
		const memberIds = byId.value[groupId]?.memberIds
		if (memberIds && !memberIds.includes(userId)) {
			memberIds.push(userId)
		}
	}

	// Local-only: remove a (deleted) user from every group
	function forgetMember(userId) {
		for (const group of Object.values(byId.value)) {
			if (group.memberIds) {
				group.memberIds = group.memberIds.filter((id) => id !== userId)
			}
		}
	}

	async function addMember(groupId, userId) {
		await axios.post(groupUrl(groupId, `/members/${encodeURIComponent(userId)}`))
		linkMember(groupId, userId)
	}

	async function removeMember(groupId, userId) {
		await axios.delete(groupUrl(groupId, `/members/${encodeURIComponent(userId)}`))
		const group = byId.value[groupId]
		if (group?.memberIds) {
			group.memberIds = group.memberIds.filter((id) => id !== userId)
		}
	}

	return {
		byId,
		ids,
		loading,
		loaded,
		list,
		upsert,
		avatarUrl,
		membersOf,
		groupsOfUser,
		fetchAll,
		create,
		update,
		uploadAvatar,
		linkMember,
		forgetMember,
		addMember,
		removeMember,
	}
})
