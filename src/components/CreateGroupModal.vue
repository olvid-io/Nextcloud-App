<template>
	<NcDialog
		:name="t('olvid', 'Create Group')"
		:open="true"
		@update:open="$emit('close')">
		<div class="create-group-modal">
			<NcTextField
				v-model="form.id"
				:label="t('olvid', 'Group Name')"
				:placeholder="t('olvid', 'e.g. members')"
				:required="true" />

			<p v-if="error" class="create-group-modal__error">
				{{ error }}
			</p>
		</div>

		<template #actions>
			<NcButton @click="$emit('close')">
				{{ t('olvid', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="saving || !form.id"
				@click="submit">
				{{ saving ? t('olvid', 'Creating…') : t('olvid', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useGroupsStore } from '../stores/groups.js'

const emit = defineEmits(['close'])

const groups = useGroupsStore()
const router = useRouter()

const form = reactive({ id: '' })
const saving = ref(false)
const error = ref(null)

async function submit() {
	if (!form.id) {
		return
	}
	saving.value = true
	error.value = null
	try {
		const group = await groups.create(form.id)
		router.push({ name: 'group-detail', params: { groupId: group.id } })
		emit('close')
	} catch (e) {
		error.value = e.response?.data?.error ?? e.message
	} finally {
		saving.value = false
	}
}
</script>

<style scoped lang="scss">
.create-group-modal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 4px 0;

	&__error {
		color: var(--color-error-text);
		margin: 0;
	}
}
</style>
