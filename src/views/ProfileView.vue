<template>
	<NcAppContent>
		<div v-if="loading" class="profile-view profile-view--centered">
			<NcLoadingIcon :size="44" />
		</div>

		<div v-else class="profile-view">
			<!-- ══ Step: install ═══════════════════════════════════════════════ -->
			<template v-if="step === 'install'">
				<div class="profile-view__step">
					<img :src="appLogoUrl"
						class="profile-view__logo"
						alt="Olvid"
						aria-hidden="true">
					<h2>{{ t('olvid', 'Link your Olvid identity') }}</h2>
					<p class="profile-view__desc">
						{{ t('olvid', 'Olvid is a privacy friendly and secure messaging application. Link your Olvid profile to your Nextcloud account to appear in the directory and be part of Olvid discussions.') }}
					</p>
					<p class="profile-view__desc">
						{{ t('olvid', 'If you do not have Olvid yet, download it first:') }}
					</p>
					<a href="https://olvid.io/download/fr/"
						target="_blank"
						rel="noopener noreferrer"
						class="profile-view__download-link">
						<NcButton>{{ t('olvid', 'Download Olvid') }}</NcButton>
					</a>
					<div class="profile-view__divider" />
					<NcButton variant="primary" @click="step = 'identity'">
						{{ t('olvid', 'I already have Olvid →') }}
					</NcButton>
				</div>
			</template>

			<!-- ══ Step: identity ══════════════════════════════════════════════ -->
			<template v-else-if="step === 'identity'">
				<div class="profile-view__step">
					<h2>{{ t('olvid', 'Your Olvid identity') }}</h2>
					<p class="profile-view__desc">
						{{ t('olvid', 'Choose how you will appear to other Olvid users. At least a first name or last name is required.') }}
					</p>

					<div class="profile-view__form">
						<NcTextField v-model="form.firstname" :label="t('olvid', 'First name')" />
						<NcTextField v-model="form.lastname" :label="t('olvid', 'Last name')" />
						<NcTextField v-model="form.position" :label="t('olvid', 'Position')" />
						<NcTextField v-model="form.company" :label="t('olvid', 'Company')" />
					</div>

					<p v-if="formError" class="profile-view__error">
						{{ formError }}
					</p>
					<p v-if="magicLinkError" class="profile-view__error">
						{{ magicLinkError }}
					</p>

					<div class="profile-view__actions">
						<NcButton @click="step = 'install'">
							{{ t('olvid', '← Back') }}
						</NcButton>
						<NcButton variant="primary" :disabled="magicLinkLoading" @click="generateMagicLink">
							{{ magicLinkLoading ? t('olvid', 'Generating…') : t('olvid', 'Generate Magic Link') }}
						</NcButton>
					</div>
				</div>
			</template>

			<!-- ══ Step: link ══════════════════════════════════════════════════ -->
			<template v-else-if="step === 'link'">
				<div class="profile-view__step">
					<h2 style="margin: auto;">
						{{ t('olvid', 'Scan with Olvid') }}
					</h2>
					<OlvidQrDisplay :configurationUrl="magicLink" />
					<p class="profile-view__polling-hint">
						{{ t('olvid', 'Waiting for you to complete enrollment in the app…') }}
					</p>
					<NcButton @click="step = 'identity'">
						{{ t('olvid', '← Back') }}
					</NcButton>
				</div>
			</template>

			<!-- ══ Step: enrolled ══════════════════════════════════════════════ -->
			<template v-else-if="step === 'enrolled'">
				<div class="profile-view__step profile-view__step--centered">
					<img :src="appLogoUrl"
						class="profile-view__success-icon"
						alt=""
						aria-hidden="true">
					<h2>{{ t('olvid', 'Identity linked!') }}</h2>
					<p class="profile-view__desc">
						{{ t('olvid', 'Your Olvid identity is now linked to your Nextcloud account. You will appear in the Olvid directory and can join group discussions.') }}
					</p>
					<NcButton variant="primary" @click="enterRegistered">
						{{ t('olvid', 'View my profile') }}
					</NcButton>
				</div>
			</template>

			<!-- ══ Step: registered ════════════════════════════════════════════ -->
			<template v-else-if="step === 'registered'">
				<!-- Section: identity details ──────────────────────────────── -->
				<section class="profile-view__section">
					<div class="profile-view__section-header">
						<h2>{{ t('olvid', 'Olvid Profile') }}</h2>

						<p class="profile-view__desc">
							{{ t('olvid', 'Change here how you appear in Olvid application.') }}
						</p>

						<h3 style="margin: 0; display: flex; justify-content: center;">
							{{ olvidDisplayNameFromForm }}
						</h3>
					</div>

					<div class="profile-view__form">
						<NcTextField v-model="form.firstname" :label="t('olvid', 'First name')" />
						<NcTextField v-model="form.lastname" :label="t('olvid', 'Last name')" />
						<NcTextField v-model="form.position" :label="t('olvid', 'Position')" />
						<NcTextField v-model="form.company" :label="t('olvid', 'Company')" />
					</div>

					<p v-if="saveError" class="profile-view__error">
						{{ saveError }}
					</p>
					<p v-if="saveSuccess" class="profile-view__success">
						{{ t('olvid', 'Profile saved.') }}
					</p>

					<div class="profile-view__actions">
						<NcButton variant="primary" :disabled="saving" @click="saveProfile">
							{{ saving ? t('olvid', 'Saving…') : t('olvid', 'Save') }}
						</NcButton>
					</div>

					<!-- Unlink/Revoke identity ───────────────────────────────────── -->
					<div class="profile-view__revoke">
						<NcButton variant="error" @click="unlinkModalOpen = true">
							{{ t('olvid', 'I no longer have access to my Olvid profile') }}
						</NcButton>

						<UnlinkIdentityModal
							v-if="unlinkModalOpen"
							:user="me.user"
							isSelf
							@close="unlinkModalOpen = false"
							@unlinked="onIdentityUnlinked" />
					</div>

					<div class="profile-view__groups">
						<!-- Section: groups ────────────────────────────────────────── -->
						<NcLoadingIcon v-if="groupsLoading" :size="32" />

						<NcEmptyContent
							v-else-if="!me.groups.length"
							:name="t('olvid', 'No groups')"
							:description="t('olvid', 'You are not a member of any group.')" />

						<div v-if="me.groups.length">
							<h4>{{ t('olvid', 'Your Groups') }}</h4>
							<p class="profile-view__desc">
								{{ t('olvid', 'The Nextcloud groups you are member of.') }}
							</p>
							<ul class="profile-view__groups-list">
								<NcListItem
									v-for="group in me.groups"
									:key="group.id"
									:name="group.displayName"
									@click="openGroup(group)">
									<template #icon>
										<OlvidAvatar
											:displayName="group.displayName"
											:isNoUser="true"
											:useOlvid="group.enabled"
											:url="groups.avatarUrl(group.id)" />
									</template>
									<template #subname>
										{{ group.enabled ? t('olvid', 'Olvid discussion enabled') : t('olvid', 'Olvid discussion disabled') }}
									</template>
								</NcListItem>
							</ul>
						</div>
					</div>
				</section>
			</template>
		</div>
	</NcAppContent>
</template>

<script setup>
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import OlvidAvatar from '../components/OlvidAvatar.vue'
import OlvidQrDisplay from '../components/OlvidQrDisplay.vue'
import UnlinkIdentityModal from '../components/UnlinkIdentityModal.vue'
import { useGroupsStore } from '../stores/groups.js'
import { useMeStore } from '../stores/me.js'

const me = useMeStore()
const groups = useGroupsStore()
const router = useRouter()

const appLogoUrl = generateFilePath('olvid', 'img', 'olvid.svg')

const loading = ref(true)
// step: 'install' | 'identity' | 'link' | 'enrolled' | 'registered'
const step = ref('install')
// Local draft of the Olvid profile fields (wizard + profile form)
const form = reactive({ firstname: '', lastname: '', position: '', company: '' })

// identity step
const formError = ref(null)
const magicLink = ref(null)
const magicLinkLoading = ref(false)
const magicLinkError = ref(null)

// link step (polling)
let pollInterval = null

// registered step — profile save
const saving = ref(false)
const saveError = ref(null)
const saveSuccess = ref(false)

// registered step — unlink/revoke identity
const unlinkModalOpen = ref(false)

// registered step — groups (all the managed groups user is member of, from the me store)
const groupsLoading = ref(false)

const olvidDisplayNameFromForm = computed(() => {
	const name = `${form.firstname} ${form.lastname}`.trim()
	const role = [form.position, form.company].filter(Boolean).join(' @ ')
	return role ? `${name} (${role})` : name
})

watch(step, (newStep) => {
	if (newStep === 'link') {
		startPolling()
	} else {
		stopPolling()
	}
})

onMounted(async () => {
	try {
		const user = await me.load()
		fillForm(user)
		if (user.useOlvid) {
			step.value = 'registered'
			await fetchGroups()
		}
	} catch (e) {
		console.error('Could not fetch Olvid status', e)
		showError(t('olvid', 'Could not fetch user'))
	} finally {
		loading.value = false
	}
})

onBeforeUnmount(stopPolling)

function fillForm(user) {
	form.firstname = user.firstname ?? ''
	form.lastname = user.lastname ?? ''
	form.position = user.position ?? ''
	form.company = user.company ?? ''
}

// ── enrollment flow ────────────────────────────────────────────────────
async function generateMagicLink() {
	if (!form.firstname.trim() && !form.lastname.trim()) {
		formError.value = t('olvid', 'At least a first name or last name is required.')
		return
	}
	formError.value = null
	magicLinkLoading.value = true
	magicLinkError.value = null
	try {
		await me.save({ ...form })
		magicLink.value = await me.getMagicLink()
		step.value = 'link'
	} catch (e) {
		magicLinkError.value = t('olvid', 'Could not generate magic link: {error}', { error: e.response?.data?.error ?? e.message })
	} finally {
		magicLinkLoading.value = false
	}
}

function startPolling() {
	stopPolling()
	pollInterval = setInterval(async () => {
		try {
			const user = await me.fetch()
			if (user.useOlvid) {
				stopPolling()
				fillForm(user)
				step.value = 'enrolled'
			}
		} catch (e) {
			console.error('Polling error', e)
		}
	}, 5000)
}

function stopPolling() {
	if (pollInterval) {
		clearInterval(pollInterval)
		pollInterval = null
	}
}

async function enterRegistered() {
	step.value = 'registered'
	await fetchGroups()
}

// ── registered — profile save ──────────────────────────────────────────
async function saveProfile() {
	saving.value = true
	saveError.value = null
	saveSuccess.value = false
	try {
		await me.save({ ...form })
		saveSuccess.value = true
	} catch (e) {
		saveError.value = t('olvid', 'Could not save profile: {error}', { error: e.response?.data?.error ?? e.message })
	} finally {
		saving.value = false
	}
}

// ── registered — unlink ────────────────────────────────────────────────
function onIdentityUnlinked() {
	unlinkModalOpen.value = false
	magicLink.value = null
	step.value = 'install'
}

// ── registered — groups ────────────────────────────────────────────────
async function fetchGroups() {
	groupsLoading.value = true
	try {
		await me.fetchGroups()
	} catch (e) {
		console.error('Could not load groups', e)
		showError(t('olvid', 'Could not load groups'))
	} finally {
		groupsLoading.value = false
	}
}

function openGroup(group) {
	if (me.isAdmin) {
		router.push({ name: 'group-detail', params: { groupId: group.id } })
	}
}
</script>

<style scoped lang="scss">
.profile-view {
	display: flex;
	flex-direction: column;
	gap: 48px;
	max-width: 520px;
	margin: 40px auto;
	padding: 0 16px;

	&--centered {
		align-items: center;
		text-align: center;
	}

	// ── Enrollment steps (install / identity / link / enrolled) ──────────
	&__step {
		display: flex;
		flex-direction: column;
		gap: 16px;

		h2 {
			margin: 0;
		}

		&--centered {
			align-items: center;
			text-align: center;
		}
	}

	&__logo {
		width: 56px;
		height: 56px;
	}

	&__success-icon {
		width: 56px;
		height: 56px;
	}

	&__desc {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}

	&__download-link {
		align-self: flex-start;
		text-decoration: none;
	}

	&__divider {
		border-top: 1px solid var(--color-border);
		margin: 8px 0;
	}

	&__actions {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
	}

	&__polling-hint {
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-style: italic;
		text-align: center;
	}

	// ── Registered sections ────────────────────────────────────────────────
	&__section {
		display: flex;
		flex-direction: column;
		gap: 16px;
		padding-bottom: 32px;
		border-bottom: 1px solid var(--color-border);

		&:last-child {
			border-bottom: none;
		}

		h2 {
			margin: 0;
		}
	}

	&__section-header {
		display: flex;
		align-items: center;
		gap: 12px;
		flex-wrap: wrap;
	}

	&__form {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	&__revoke, &__groups {
		margin-top: 8px;
		padding-top: 16px;
		border-top: 1px solid var(--color-border);
	}

	&__groups-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}

	// ── Feedback messages ──────────────────────────────────────────────────
	&__error {
		color: var(--color-error);
		margin: 0;
	}

	&__success {
		color: var(--color-success);
		margin: 0;
	}
}
</style>
