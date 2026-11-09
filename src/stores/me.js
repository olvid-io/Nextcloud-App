import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useGroupsStore } from './groups.js'
import { useUsersStore } from './users.js'

/**
 * Current user. The user object itself lives in the users store (so editing yourself
 * as an admin and from the profile page stays in sync); this store keeps the session data.
 */
export const useMeStore = defineStore('me', () => {
	const id = ref(null)
	const isAdmin = ref(false)
	/** null until checked */
	const configurationValid = ref(null)
	/** Groups the current user is member of, from GET /app/me/groups. */
	const groupIds = ref([])
	let loadPromise = null

	const user = computed(() => (id.value ? useUsersStore().byId[id.value] : null))
	const groups = computed(() => {
		const groupsStore = useGroupsStore()
		return groupIds.value.map((groupId) => groupsStore.byId[groupId]).filter(Boolean)
	})

	/** Always fetches GET /app/me (used for enrollment polling). */
	async function fetch() {
		const res = await axios.get(generateOcsUrl('/apps/olvid/app/me'))
		const { isAdmin: admin, ...me } = res.data
		isAdmin.value = admin ?? false
		id.value = me.id
		return useUsersStore().upsert(me)
	}

	/** Fetches GET /app/me once, concurrent callers share the same request. */
	function load() {
		loadPromise ??= fetch().catch((e) => {
			loadPromise = null
			throw e
		})
		return loadPromise
	}

	async function checkConfiguration() {
		const res = await axios.get(generateOcsUrl('/apps/olvid/app/isAppConfigurationValid'))
		configurationValid.value = res.data.isConfigurationValid
	}

	async function save(form) {
		await axios.put(generateOcsUrl('/apps/olvid/app/me'), form)
		useUsersStore().upsert({ id: id.value, ...form })
	}

	async function getMagicLink() {
		const res = await axios.get(generateOcsUrl('/apps/olvid/app/me/getMagicLink'))
		return res.data.configurationUrl
	}

	async function unlink(revoke) {
		await axios.delete(generateOcsUrl('/apps/olvid/app/me/identity'), { data: { revoke } })
		useUsersStore().upsert({ id: id.value, useOlvid: false })
	}

	async function fetchGroups() {
		const res = await axios.get(generateOcsUrl('/apps/olvid/app/me/groups'))
		const groupsStore = useGroupsStore()
		groupIds.value = (res.data.groups ?? []).map((group) => groupsStore.upsert(group).id)
	}

	return { id, isAdmin, configurationValid, groupIds, user, groups, fetch, load, checkConfiguration, save, getMagicLink, unlink, fetchGroups }
})
