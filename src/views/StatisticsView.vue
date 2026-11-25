<template>
	<NcAppContent>
		<div class="statistics-view__header">
			<h2 class="statistics-view__title">
				{{ t('olvid', 'Statistics') }}
			</h2>
			<p class="statistics-view__desc">
				{{ t('olvid', 'An overview of how Olvid is used on this Nextcloud: how many users have linked their Olvid profile, how many groups have an Olvid discussion, and how many linked profiles seem inactive.') }}
			</p>
		</div>

		<div v-if="loading" class="statistics-view__loading">
			<NcLoadingIcon :size="44" />
		</div>

		<div v-else class="statistics-view__cards">
			<section v-for="card in cards" :key="card.id" class="statistics-card">
				<h3 class="statistics-card__title">
					{{ card.title }}
				</h3>

				<p class="statistics-card__figure">
					<span class="statistics-card__count">{{ card.count }}</span>
					<span class="statistics-card__total">{{ card.total }}</span>
				</p>

				<NcProgressBar
					:value="card.percent"
					:color="card.color"
					:aria-label="card.title" />

				<p class="statistics-card__desc">
					{{ card.description }}
				</p>

				<NcButton
					class="statistics-card__action"
					variant="tertiary"
					@click="router.push({ name: card.route })">
					<template #icon>
						<IconArrowRight :size="20" />
					</template>
					{{ card.action }}
				</NcButton>
			</section>
		</div>
	</NcAppContent>
</template>

<script setup>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcProgressBar from '@nextcloud/vue/components/NcProgressBar'
import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'

const router = useRouter()

// read-only snapshot, refetched every time the view is shown (no shared state)
const statistics = ref({
	olvidUsersCount: 0,
	nextcloudUsersCount: 0,
	olvidInactiveUsers: 0,
	olvidGroupsCount: 0,
	nextcloudGroupsCount: 0,
})
const loading = ref(true)

function percent(count, total) {
	return total ? Math.round((count / total) * 100) : 0
}

const cards = computed(() => {
	const s = statistics.value
	const linkedPercent = percent(s.olvidUsersCount, s.nextcloudUsersCount)
	const groupsPercent = percent(s.olvidGroupsCount, s.nextcloudGroupsCount)
	const inactivePercent = percent(s.olvidInactiveUsers, s.olvidUsersCount)

	return [
		{
			id: 'users',
			title: t('olvid', 'Linked Olvid profiles'),
			count: s.olvidUsersCount,
			total: n('olvid', 'of {total} Nextcloud user ({percent}%)', 'of {total} Nextcloud users ({percent}%)', s.nextcloudUsersCount, { total: s.nextcloudUsersCount, percent: linkedPercent }),
			percent: linkedPercent,
			color: 'var(--color-primary-element)',
			description: s.olvidUsersCount
				? t('olvid', 'Users who have linked their Olvid profile appear in the Olvid directory: other users can find them and add them as contacts.')
				: t('olvid', 'No user has linked their Olvid profile yet. Send a magic link to a user from the Users page to help them get started.'),
			action: t('olvid', 'See users'),
			route: 'users',
		},
		{
			id: 'groups',
			title: t('olvid', 'Groups with an Olvid discussion'),
			count: s.olvidGroupsCount,
			total: n('olvid', 'of {total} Nextcloud group ({percent}%)', 'of {total} Nextcloud groups ({percent}%)', s.nextcloudGroupsCount, { total: s.nextcloudGroupsCount, percent: groupsPercent }),
			percent: groupsPercent,
			color: 'var(--color-primary-element)',
			description: s.olvidGroupsCount
				? t('olvid', 'Members of these groups share a secure group discussion in Olvid. Other groups are not visible in Olvid.')
				: t('olvid', 'No group has an Olvid discussion yet. Turn it on for a group from the Groups page.'),
			action: t('olvid', 'See groups'),
			route: 'groups',
		},
		{
			id: 'inactive',
			title: t('olvid', 'Inactive Olvid profiles'),
			count: s.olvidInactiveUsers,
			total: n('olvid', 'of {total} linked Olvid profile ({percent}%)', 'of {total} linked Olvid profiles ({percent}%)', s.olvidUsersCount, { total: s.olvidUsersCount, percent: inactivePercent }),
			percent: inactivePercent,
			color: 'var(--color-warning)',
			description: t('olvid', 'Linked profiles whose Olvid app has not contacted this Nextcloud for more than 2 weeks. The user may have changed phone or uninstalled Olvid: if they lost access to their profile, they can unlink it from the Olvid app in Nextcloud and link a new one.'),
			action: t('olvid', 'See users'),
			route: 'users',
		},
	]
})

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
.statistics-view {
	// same header as the Users and Groups views
	&__header {
		display: flex;
		flex-direction: column;
		gap: 4px;
		// leave room for the navigation toggle button on the top-left corner
		padding: 8px 16px 16px calc(var(--default-clickable-area) + 16px);
		border-bottom: 1px solid var(--color-border);
	}

	&__title {
		margin: 0;
		font-size: 1.2rem;
		font-weight: bold;
		line-height: var(--default-clickable-area);
	}

	&__desc {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}

	&__loading {
		display: flex;
		justify-content: center;
		padding: 32px;
	}

	&__cards {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: 16px;
		padding: 16px;
	}
}

.statistics-card {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 16px;
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);

	&__title {
		margin: 0;
		font-size: 1rem;
		font-weight: bold;
	}

	&__figure {
		display: flex;
		align-items: baseline;
		flex-wrap: wrap;
		gap: 8px;
		margin: 0;
	}

	&__count {
		font-size: 2rem;
		font-weight: bold;
		line-height: 1.2;
	}

	&__total {
		color: var(--color-text-maxcontrast);
	}

	&__desc {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
		// keep the action buttons aligned at the bottom of every card
		flex-grow: 1;
	}

	&__action {
		align-self: flex-start;
	}
}
</style>
