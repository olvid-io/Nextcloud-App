import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useGroupsStore } from './groups.js'

function userUrl(userId, suffix = '') {
	return generateOcsUrl(`/apps/olvid/app/users/${encodeURIComponent(userId)}${suffix}`)
}

/**
 * Single source of truth for users. Every user payload received from the API (full users,
 * group members, search results, current user) is merged into the same object, so any
 * component rendering a user is updated whenever that user changes.
 */
export const useUsersStore = defineStore('users', () => {
	/** All known users, by id (OlvidUser or OlvidUserFull, merged). */
	const byId = ref({})
	/** Ids returned by GET /app/users, in server order. */
	const ids = ref([])
	const loading = ref(false)
	const loaded = ref(false)

	const list = computed(() => ids.value.map((id) => byId.value[id]).filter(Boolean))

	/**
	 * Merge a user payload into the store, keeping object identity.
	 *
	 * @param {object} user user payload, must have an id
	 * @return {object} the stored (reactive) user
	 */
	function upsert(user) {
		const existing = byId.value[user.id]
		if (existing) {
			Object.assign(existing, user)
		} else {
			byId.value[user.id] = user
		}
		return byId.value[user.id]
	}

	async function fetchAll() {
		loading.value = true
		try {
			const res = await axios.get(generateOcsUrl('/apps/olvid/app/users'))
			ids.value = (res.data.users ?? []).map((user) => upsert(user).id)
			loaded.value = true
		} finally {
			loading.value = false
		}
	}

	async function create(form) {
		const res = await axios.post(generateOcsUrl('/apps/olvid/app/users'), form)
		const user = upsert(res.data)
		ids.value.push(user.id)
		const groups = useGroupsStore()
		for (const groupId of form.groups ?? []) {
			groups.linkMember(groupId, user.id)
		}
		return user
	}

	async function update(userId, form) {
		await axios.put(userUrl(userId), form)
		upsert({ id: userId, ...form })
	}

	async function remove(userId, { revoke = false } = {}) {
		if (revoke) {
			await axios.delete(userUrl(userId, '/identity'), { data: { revoke: true } })
		}
		await axios.delete(userUrl(userId))
		ids.value = ids.value.filter((id) => id !== userId)
		delete byId.value[userId]
		useGroupsStore().forgetMember(userId)
	}

	async function unlinkIdentity(userId, revoke) {
		await axios.delete(userUrl(userId, '/identity'), { data: { revoke } })
		upsert({ id: userId, useOlvid: false })
	}

	async function getMagicLink(userId) {
		const res = await axios.get(userUrl(userId, '/magicLink'))
		return res.data.configurationUrl
	}

	/**
	 * @param {string} query search string
	 * @return {Promise<string[]>} ids of matching users
	 */
	async function search(query) {
		const res = await axios.get(generateOcsUrl('/apps/olvid/app/users/search'), { params: { query } })
		return (res.data.users ?? []).map((user) => upsert(user).id)
	}

	return { byId, ids, loading, loaded, list, upsert, fetchAll, create, update, remove, unlinkIdentity, getMagicLink, search }
})
