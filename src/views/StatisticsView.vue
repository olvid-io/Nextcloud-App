<template>
	<NcAppContent>
		<NcLoadingIcon v-if="loading" :size="44" />

		<div v-else style="display: flex;">
			<section class="statistics-section" style="margin: auto;">
				<div class="statistics">
					<h3>{{ t('olvid', 'Olvid Users') }}</h3>
					<p>{{ statistics.olvidUsersCount }} / {{ statistics.nextcloudUsersCount }}</p>
				</div>
				<div class="statistics">
					<h3>{{ t('olvid', 'Olvid Groups') }}</h3>
					<p>{{ statistics.olvidGroupsCount }} / {{ statistics.nextcloudGroupsCount }}</p>
				</div>
				<div class="statistics">
					<h3>{{ t('olvid', 'Inactive Olvid users') }}</h3>
					<p>{{ statistics.olvidInactiveUsers }} / {{ statistics.olvidUsersCount }}</p>
				</div>
			</section>
		</div>
	</NcAppContent>
</template>

<script setup>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { onMounted, ref } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

// read-only snapshot, refetched every time the view is shown (no shared state)
const statistics = ref({
	olvidUsersCount: 0,
	nextcloudUsersCount: 0,
	olvidInactiveUsers: 0,
	olvidGroupsCount: 0,
	nextcloudGroupsCount: 0,
})
const loading = ref(true)

onMounted(async () => {
	try {
		const res = await axios.get(generateOcsUrl('/apps/olvid/app/statistics'))
		statistics.value = res.data
	} catch (e) {
		console.error('Could not load statistics', e)
		showError(t('olvid', 'Could not load statistics'))
	} finally {
		loading.value = false
	}
})
</script>

<style scoped lang="scss">
.statistics-section {
	display: flex;
	gap: 20px;
	flex-wrap: wrap;
	padding-top: 20px;
}

.statistics {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: space-evenly;
	border: 1px #b0b0b0 solid;
	border-radius: 15px;
	padding: 10px;
	width: 300px;
	height: 150px;
}

.statistics p {
	font-size: 2.5rem;
	font-weight: bold;
}

.statistics h3 {
	font-weight: normal;
	text-transform: uppercase;
}

.statistics h3, .statistics p {
	margin: 0;
}
</style>
