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
					<h2>{{ t('olvid', 'Link your Olvid profile') }}</h2>
					<p class="profile-view__desc">
						{{ t('olvid', 'Olvid is a privacy friendly and secure messaging application.') }}
					</p>
					<p class="profile-view__desc">
						{{ t('olvid', 'Link your Olvid profile to your Nextcloud account to chat with other users and be part of Olvid discussions.') }}
					</p>
					<div class="profile-view__divider" />
					<NcButton variant="primary" @click="step = 'profile'">
						{{ t('olvid', 'Start') }}
					</NcButton>
				</div>
			</template>

			<!-- ══ Step: profile ══════════════════════════════════════════════ -->
			<template v-else-if="step === 'profile'">
				<div class="profile-view__step">
					<h2>{{ t('olvid', 'Your Olvid profile') }}</h2>
					<p class="profile-view__desc">
						{{ t('olvid', 'Enter at least your first or last name. It will be shown to your Olvid contacts.') }}
					</p>
					<div class="profile-view__form">
						<NcTextField v-model="form.firstname" :label="t('olvid', 'First name')" />
						<NcTextField v-model="form.lastname" :label="t('olvid', 'Last name')" />
						<NcTextField v-model="form.position" :label="t('olvid', 'Position (optional)')" />
						<NcTextField v-model="form.company" :label="t('olvid', 'Company (optional)')" />
					</div>

					<p v-if="magicLinkError" class="profile-view__error">
						{{ magicLinkError }}
					</p>

					<div class="profile-view__actions">
						<NcButton @click="step = 'install'">
							<template #icon>
								<IconArrowLeft :size="20" />
							</template>
							{{ t('olvid', 'Back') }}
						</NcButton>
						<NcButton variant="primary" :disabled="magicLinkLoading || (!form.firstname.trim() && !form.lastname.trim())" @click="validateProfile">
							{{ t('olvid', 'Validate') }}
						</NcButton>
					</div>
				</div>
			</template>

			<!-- ══ Step: link ══════════════════════════════════════════════════ -->
			<template v-else-if="step === 'link'">
				<div class="profile-view__step profile-view__step--centered">
					<NcLoadingIcon :size="44" />
					<p class="profile-view__polling-hint">
						{{ t('olvid', 'Waiting for you to link your Olvid profile in the app…') }}
					</p>
					<div class="profile-view__actions">
						<NcButton @click="step = 'profile'">
							<template #icon>
								<IconArrowLeft :size="20" />
							</template>
							{{ t('olvid', 'Back') }}
						</NcButton>
						<NcButton alignment="start-reverse" @click="openMagicLink">
							<template #icon>
								<IconOpenInNew :size="20" />
							</template>
							{{ t('olvid', 'Show magic link') }}
						</NcButton>
					</div>
				</div>
			</template>

			<!-- ══ Step: enrolled ══════════════════════════════════════════════ -->
			<template v-else-if="step === 'enrolled'">
				<div class="profile-view__step profile-view__step--centered">
					<img :src="appLogoUrl"
						class="profile-view__success-icon"
						alt=""
						aria-hidden="true">
					<h2>{{ t('olvid', 'Olvid profile linked!') }}</h2>
					<p class="profile-view__desc">
						{{ t('olvid', 'Your Olvid profile is now linked to your Nextcloud account. You will appear in the Olvid directory and can join group discussions.') }}
					</p>
					<NcButton variant="primary" @click="enterRegistered">
						{{ t('olvid', 'View my profile') }}
					</NcButton>
				</div>
			</template>

			<!-- ══ Step: registered ════════════════════════════════════════════ -->
			<template v-else-if="step === 'registered'">
				<!-- Section: olvid profile ──────────────────────────────── -->
				<section class="profile-view__section">
					<div class="profile-view__section-header">
						<img :src="appLogoUrl"
							class="profile-view__intro-logo"
							alt=""
							aria-hidden="true">
						<h2>{{ t('olvid', 'Olvid profile') }}</h2>
					</div>

					<div class="profile-view__preview">
						<span class="profile-view__preview-label">
							{{ t('olvid', 'This is how other Olvid users see you:') }}
						</span>

						<div class="profile-view__preview-card">
							<OlvidAvatar
								:displayName="previewName || '?'"
								:useOlvid="true"
								:size="44"
								:disableMenu="true"
								:disableTooltip="true" />

							<div class="profile-view__preview-text">
								<strong :class="{ 'profile-view__preview-placeholder': !previewName }">
									{{ previewName || t('olvid', 'Enter your first and last name') }}
								</strong>
								<span v-if="previewRole" class="profile-view__preview-role">
									{{ previewRole }}
								</span>
							</div>
						</div>
					</div>

					<div class="profile-view__form">
						<NcTextField v-model="form.firstname" :label="t('olvid', 'First name')" />
						<NcTextField v-model="form.lastname" :label="t('olvid', 'Last name')" />
						<NcTextField v-model="form.position" :label="t('olvid', 'Position (optional)')" />
						<NcTextField v-model="form.company" :label="t('olvid', 'Company (optional)')" />
					</div>

					<p v-if="saveError" class="profile-view__error">
						{{ saveError }}
					</p>
					<p v-if="saveSuccess" class="profile-view__success">
						{{ t('olvid', 'Profile saved.') }}
					</p>

					<div class="profile-view__actions">
						<NcButton variant="primary" :disabled="saving || !hasChanges" @click="saveProfile">
							{{ saving ? t('olvid', 'Saving…') : t('olvid', 'Save changes') }}
						</NcButton>
					</div>
				</section>

				<!-- Intro: reminds what Olvid is and what linking the profile does -->
				<div class="profile-view__intro">
					<div class="profile-view__intro-text">
						<p>{{ t('olvid', 'Olvid is a secure, privacy-friendly messaging app. Your Olvid profile is linked to your Nextcloud account, so:') }}</p>
						<ul>
							<li>{{ t('olvid', 'other users of this Nextcloud can find you in the Olvid directory and add you as a contact;') }}</li>
							<li>{{ t('olvid', 'you are automatically added to the Olvid discussions of your groups, and removed from them when you leave a group;') }}</li>
							<li>{{ t('olvid', 'the name, position and company below are what your Olvid contacts see. Changes you save here are applied to your Olvid profile automatically.') }}</li>
						</ul>
						<p>{{ t('olvid', 'To chat, open the Olvid app on your phone or computer.') }}</p>
					</div>
				</div>

				<!-- Section: lost access (unlink / revoke identity) ──────────── -->
				<section class="profile-view__section profile-view__recovery">
					<h3>{{ t('olvid', 'Lost access to your Olvid profile?') }}</h3>
					<p class="profile-view__desc">
						{{ t('olvid', 'If you changed phone without transferring Olvid, uninstalled the app or deleted your profile, your Nextcloud account is still linked to a profile you can no longer use. Unlink it to link a new Olvid profile.') }}
					</p>
					<p class="profile-view__hint">
						{{ t('olvid', 'Restored Olvid from a backup or transferred it to your new device? You have nothing to do, your profile is still linked.') }}
					</p>
					<div class="profile-view__actions">
						<NcButton @click="unlinkModalOpen = true">
							<template #icon>
								<IconLinkVariantOff :size="20" />
							</template>
							{{ t('olvid', 'Unlink my Olvid profile…') }}
						</NcButton>
					</div>

					<UnlinkIdentityModal
						v-if="unlinkModalOpen"
						:user="me.user"
						isSelf
						@close="unlinkModalOpen = false"
						@unlinked="onIdentityUnlinked" />
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
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconLinkVariantOff from 'vue-material-design-icons/LinkVariantOff.vue'
import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import OlvidAvatar from '../components/OlvidAvatar.vue'
import UnlinkIdentityModal from '../components/UnlinkIdentityModal.vue'
import { useMeStore } from '../stores/me.js'

const me = useMeStore()

const appLogoUrl = generateFilePath('olvid', 'img', 'olvid.svg')

const loading = ref(true)
// step: 'install' | 'profile' | 'link' | 'enrolled' | 'registered'
const step = ref('install')
// Local draft of the Olvid profile fields (wizard + profile form)
const form = reactive({ firstname: '', lastname: '', position: '', company: '' })

// profile step
const magicLink = ref(null)
let magicLinkFetchedAt = null
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

// true when the draft differs from the saved profile in the store
const hasChanges = computed(() => {
	const user = me.user
	if (!user) {
		return false
	}
	return Object.keys(form).some((key) => form[key] !== (user[key] ?? ''))
})

const previewName = computed(() => `${form.firstname} ${form.lastname}`.trim())

const previewRole = computed(() => {
	const position = form.position.trim()
	const company = form.company.trim()
	if (position && company) {
		return t('olvid', '{position} @ {company}', { position, company })
	}
	return position || company
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
// save profile and open magic link in a new tab
async function validateProfile() {
	magicLinkLoading.value = true
	magicLinkError.value = null
	const win = window.open('', '_blank')
	try {
		await me.save({ ...form })
		await ensureMagicLink()
		if (win) {
			win.location = magicLink.value
		} else {
			window.open(magicLink.value, '_blank')
		}
		step.value = 'link'
	} catch (e) {
		win?.close()
		magicLinkError.value = t('olvid', 'Could not generate magic link: {error}', { error: e.response?.data?.error ?? e.message })
	} finally {
		magicLinkLoading.value = false
	}
}

// re-open current magic link or refresh it if it expires
async function openMagicLink() {
	const win = window.open('', '_blank')
	try {
		await ensureMagicLink()
		if (win) {
			win.location = magicLink.value
		} else {
			window.open(magicLink.value, '_blank')
		}
	} catch (e) {
		win?.close()
		showError(t('olvid', 'Could not generate magic link: {error}', { error: e.response?.data?.error ?? e.message }))
	}
}

async function ensureMagicLink() {
	// links expire after 30 min, refresh slightly before
	if (!magicLink.value || Date.now() - magicLinkFetchedAt > 25 * 60 * 1000) {
		magicLink.value = await me.getMagicLink(30 * 60)
		magicLinkFetchedAt = Date.now()
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

function enterRegistered() {
	step.value = 'registered'
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

</script>

<style scoped lang="scss">
.profile-view {
	display: flex;
	flex-direction: column;
	gap: 32px; // = section padding-bottom, so dividers are centered
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

		h2, h3 {
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

	&__preview {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}

	&__preview-label {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
	}

	&__preview-card {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 10px 14px;
		max-width: 420px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background: var(--color-background-hover);
	}

	&__preview-text {
		display: flex;
		flex-direction: column;
		min-width: 0; // allow ellipsis

		> * {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}

	&__preview-role {
		color: var(--color-text-maxcontrast);
	}

	&__preview-placeholder {
		font-weight: normal;
		font-style: italic;
		color: var(--color-text-maxcontrast);
	}

	// Secondary help section: low emphasis, the destructive styling lives in the dialog
	&__recovery {
		gap: 8px;

		.profile-view__actions {
			margin-top: 8px;
		}
	}

	// compact intro on the registered page: small logo + muted text
	&__intro {
		display: flex;
		align-items: flex-start;
		gap: 12px;
	}

	&__intro-logo {
		width: 32px;
		height: 32px;
		flex-shrink: 0;
	}

	&__intro-text {
		color: var(--color-text-maxcontrast);
		line-height: 1.5;

		p {
			margin: 0;
		}

		ul {
			margin: 4px 0;
			padding-inline-start: 1.2em;
			list-style: disc;
		}
	}

	&__hint {
		margin: 0;
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		font-style: italic;
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
