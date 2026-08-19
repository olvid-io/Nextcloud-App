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

<script>

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

export default {
	name: 'StatisticsView',
	components: { NcAppContent, NcLoadingIcon },

	emits: [],

	data() {
		return {
			statistics: {
				olvidUsersCount: Number,
				nextcloudUsersCount: Number,
				olvidInactiveUsers: Number,
				olvidGroupsCount: Number,
				nextcloudGroupsCount: Number,
			},
			loading: true,
		}
	},

	async mounted() {
		await this.fetchStatistics()
		this.openUserFromRoute()
	},

	methods: {
		async fetchStatistics() {
			this.loading = true
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/statistics'))
				this.statistics = res.data ?? { olvidUsersCount: 0, nextcloudUsersCount: 0 }
			} catch (e) {
				console.error('Could not load users', e)
			} finally {
				this.loading = false
			}
		},
	},
}
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
