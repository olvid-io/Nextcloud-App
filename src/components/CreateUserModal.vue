<template>
	<NcDialog
		:name="t('olvid', 'Create User')"
		:open="true"
		@update:open="$emit('close')">
		<div class="create-user-modal">
			<NcTextField
				v-model="form.uid"
				:label="t('olvid', 'Nextcloud user ID')"
				:placeholder="t('olvid', 'e.g. jsmith')"
				:required="true" />

			<details class="create-user-modal__details">
				<summary class="create-user-modal__summary">
					{{ t('olvid', 'Olvid details (optional)') }}
				</summary>
				<div class="create-user-modal__olvid-fields">
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
				</div>
			</details>

			<details class="create-user-modal__details">
				<summary class="create-user-modal__summary">
					{{ t('olvid', 'Nextcloud password (optional)') }}
				</summary>
				<div class="create-user-modal__olvid-fields">
					<div>{{ t('olvid', 'Leave blank if this user will only use Olvid and not Nextcloud.') }}</div>
					<div>{{ t('olvid', 'You can set a password later in Nextcloud account settings if you change your mind.') }}</div>

					<NcPasswordField
						v-model="form.password"
						:label="t('olvid', 'Password')"
						:required="true" />
				</div>
			</details>

			<div class="create-user-modal__item">
				<NcSelect
					v-model="form.groups"
					class="create-user-modal__select"
					:inputLabel="t('olvid', 'Member of the following groups')"
					:placeholder="t('olvid', 'Set user groups')"
					:options="allGroupIds"
					keepOpen
					:multiple="true" />
			</div>

			<p v-if="error" class="create-user-modal__error">
				{{ error }}
			</p>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || !form.uid"
				@click="submit">
				{{ saving ? t('olvid', 'Creating…') : t('olvid', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useGroupsStore } from '../stores/groups.js'
import { useUsersStore } from '../stores/users.js'

const emit = defineEmits(['close'])

const users = useUsersStore()
const groups = useGroupsStore()
const router = useRouter()

const form = reactive({
	uid: '',
	password: '',
	firstname: '',
	lastname: '',
	position: '',
	company: '',
	groups: [],
})
const saving = ref(false)
const error = ref(null)

const allGroupIds = computed(() => groups.list.map((g) => g.id))

onMounted(async () => {
	if (groups.loaded) {
		return
	}
	try {
		await groups.fetchAll()
	} catch (e) {
		console.error('Could not load groups', e)
		showError(t('olvid', 'Could not load groups'))
	}
})

async function submit() {
	if (!form.uid) {
		return
	}
	saving.value = true
	error.value = null
	try {
		const user = await users.create({ ...form })
		router.push({ name: 'user-detail', params: { userId: user.id } })
		emit('close')
	} catch (e) {
		error.value = e.response?.data?.error ?? e.message
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.create-user-modal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 4px 0;

	&__details {
		margin-top: 4px;
	}

	&__summary {
		cursor: pointer;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;
		user-select: none;
	}

	&__olvid-fields {
		display: flex;
		flex-direction: column;
		gap: 8px;
		margin-top: 8px;
	}

	&__error {
		color: var(--color-error-text);
		margin: 0;
	}
}
</style>
