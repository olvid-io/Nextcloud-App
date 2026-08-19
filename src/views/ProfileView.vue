<template>
	<NcAppContent>
		<InvalidConfigurationAlertModal v-if="showConfigurationAlert" />

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
					<NcButton type="primary" @click="step = 'identity'">
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
						<NcTextField :value.sync="form.firstname" :label="t('olvid', 'First name')" />
						<NcTextField :value.sync="form.lastname" :label="t('olvid', 'Last name')" />
						<NcTextField :value.sync="form.position" :label="t('olvid', 'Position')" />
						<NcTextField :value.sync="form.company" :label="t('olvid', 'Company')" />
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
						<NcButton type="primary" :disabled="magicLinkLoading" @click="generateMagicLink">
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
					<OlvidQrDisplay :configuration-url="magicLink" />
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
					<NcButton type="primary" @click="enterRegistered">
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
						<span class="profile-view__enrolled-badge">
							<img :src="olvidEnabledUrl"
								width="16"
								height="16"
								alt=""
								aria-hidden="true">
							{{ t('olvid', 'Enrolled') }}
						</span>
					</div>

					<div class="profile-view__form">
						<NcTextField :value.sync="form.firstname" :label="t('olvid', 'First name')" />
						<NcTextField :value.sync="form.lastname" :label="t('olvid', 'Last name')" />
						<NcTextField :value.sync="form.position" :label="t('olvid', 'Position')" />
						<NcTextField :value.sync="form.company" :label="t('olvid', 'Company')" />
					</div>

					<p v-if="saveError" class="profile-view__error">
						{{ saveError }}
					</p>
					<p v-if="saveSuccess" class="profile-view__success">
						{{ t('olvid', 'Profile saved.') }}
					</p>

					<div class="profile-view__actions">
						<NcButton type="primary" :disabled="saving" @click="saveProfile">
							{{ saving ? t('olvid', 'Saving…') : t('olvid', 'Save') }}
						</NcButton>
					</div>

					<!-- Unlink/Revoke identity ───────────────────────────────────── -->
					<div class="profile-view__revoke">
						<NcButton type="error" @click="unlinkModalOpen = true">
							{{ t('olvid', 'I no longer have access to my Olvid profile') }}
						</NcButton>

						<UnlinkIdentityModal
							v-if="unlinkModalOpen"
							:user="currentUser"
							is-self
							@close="unlinkModalOpen = false"
							@unlinked="onIdentityUnlinked" />
					</div>
				</section>

				<!-- Section: groups ────────────────────────────────────────── -->
				<section class="profile-view__section">
					<h2>{{ t('olvid', 'My Groups') }}</h2>

					<NcLoadingIcon v-if="groupsLoading" :size="32" />

					<NcEmptyContent
						v-else-if="!groups.length"
						:name="t('olvid', 'No groups')"
						:description="t('olvid', 'You are not a member of any group.')" />

					<ul v-else class="profile-view__groups-list">
						<NcListItem
							v-for="group in groups"
							:key="group.id"
							:name="group.displayName"
							@click="isAdmin && $emit('open-group-sidebar', group)">
							<template #icon>
								<OlvidAvatar
									:display-name="group.displayName"
									:is-no-user="true"
									:use-olvid="group.enabled"
									:url="group.photoUid ? buildGroupAvatarUrl(group) : undefined" />
							</template>
							<template #subname>
								{{ group.enabled ? t('olvid', 'Olvid discussion enabled') : t('olvid', 'Olvid discussion disabled') }}
							</template>
						</NcListItem>
					</ul>
				</section>
			</template>
		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateFilePath, generateOcsUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import OlvidAvatar from '../components/OlvidAvatar.vue'
import OlvidQrDisplay from '../components/OlvidQrDisplay.vue'
import UnlinkIdentityModal from '../components/UnlinkIdentityModal.vue'
import InvalidConfigurationAlertModal from '../components/InvalidConfigurationAlertModal.vue'

export default {
	name: 'ProfileView',
	components: { InvalidConfigurationAlertModal, NcAppContent, NcButton, NcEmptyContent, NcListItem, NcLoadingIcon, NcTextField, OlvidAvatar, OlvidQrDisplay, UnlinkIdentityModal },

	emits: ['open-group-sidebar'],

	data() {
		return {
			loading: true,
			// step: 'install' | 'identity' | 'link' | 'enrolled' | 'registered'
			step: 'install',
			form: { firstname: '', lastname: '', position: '', company: '' },
			currentUser: null,
			showConfigurationAlert: false,

			// identity step
			formError: null,
			magicLink: null,
			magicLinkLoading: false,
			magicLinkError: null,

			// link step (polling)
			pollInterval: null,

			// registered step — profile save
			saving: false,
			saveError: null,
			saveSuccess: false,

			// registered step — unlink/revoke identity
			unlinkModalOpen: false,

			// registered step — groups
			groups: [],
			groupsLoading: false,
			isAdmin: false,
		}
	},

	computed: {
		appLogoUrl() {
			return generateFilePath('olvid', 'img', 'olvid.svg')
		},
		olvidEnabledUrl() {
			return generateFilePath('olvid', 'img', 'olvid-enabled.svg')
		},
	},

	watch: {
		step(newStep) {
			if (newStep === 'link') {
				this.startPolling()
			} else {
				this.stopPolling()
			}
		},
	},

	async mounted() {
		try {
			const res = await axios.get(generateOcsUrl('/apps/olvid/app/me'))
			this.currentUser = res.data
			this.isAdmin = res.data.isAdmin ?? false
			this.form = {
				firstname: res.data.firstname ?? '',
				lastname: res.data.lastname ?? '',
				position: res.data.position ?? '',
				company: res.data.company ?? '',
			}
			// check in background if Olvid application configuration is valid or not, only if user is an admin and can fix it
			if (res.data.isAdmin) {
				await axios.get(generateOcsUrl('/apps/olvid/app/isAppConfigurationValid')).then(res => {
					if (res.data.isConfigurationValid === false) {
						this.showConfigurationAlert = true
					}
				})
			}
			if (res.data.useOlvid) {
				this.step = 'registered'
				await this.fetchGroups()
			}
		} catch (e) {
			console.error('Could not fetch Olvid status', e)
		} finally {
			this.loading = false
		}
	},

	beforeDestroy() {
		this.stopPolling()
	},

	methods: {
		// ── enrollment flow ────────────────────────────────────────────────────
		async generateMagicLink() {
			if (!this.form.firstname.trim() && !this.form.lastname.trim()) {
				this.formError = t('olvid', 'At least a first name or last name is required.')
				return
			}
			this.formError = null
			this.magicLinkLoading = true
			this.magicLinkError = null
			try {
				await axios.put(generateOcsUrl('/apps/olvid/app/me'), this.form)
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/me/getMagicLink'))
				this.magicLink = res.data.configurationUrl
				this.step = 'link'
			} catch (e) {
				this.magicLinkError = t('olvid', 'Could not generate magic link: {error}', { error: e.response?.data?.error ?? e.message })
			} finally {
				this.magicLinkLoading = false
			}
		},

		startPolling() {
			this.stopPolling()
			this.pollInterval = setInterval(async () => {
				try {
					const res = await axios.get(generateOcsUrl('/apps/olvid/app/me'))
					if (res.data.useOlvid) {
						this.stopPolling()
						this.currentUser = res.data
						this.form = {
							firstname: res.data.firstname ?? '',
							lastname: res.data.lastname ?? '',
							position: res.data.position ?? '',
							company: res.data.company ?? '',
						}
						this.step = 'enrolled'
					}
				} catch (e) {
					console.error('Polling error', e)
				}
			}, 5000)
		},

		stopPolling() {
			if (this.pollInterval) {
				clearInterval(this.pollInterval)
				this.pollInterval = null
			}
		},

		async enterRegistered() {
			this.step = 'registered'
			await this.fetchGroups()
		},

		// ── registered — profile save ──────────────────────────────────────────
		async saveProfile() {
			this.saving = true
			this.saveError = null
			this.saveSuccess = false
			try {
				await axios.put(generateOcsUrl('/apps/olvid/app/me'), this.form)
				this.saveSuccess = true
			} catch (e) {
				this.saveError = t('olvid', 'Could not save profile: {error}', { error: e.response?.data?.error ?? e.message })
			} finally {
				this.saving = false
			}
		},

		// ── registered — unlink ────────────────────────────────────────────────
		onIdentityUnlinked() {
			this.unlinkModalOpen = false
			this.currentUser = { ...this.currentUser, useOlvid: false }
			this.magicLink = null
			this.step = 'install'
		},

		// ── registered — groups ────────────────────────────────────────────────
		async fetchGroups() {
			this.groupsLoading = true
			try {
				const res = await axios.get(generateOcsUrl('/apps/olvid/app/me/groups'))
				this.groups = res.data.groups ?? []
			} catch (e) {
				console.error('Could not load groups', e)
			} finally {
				this.groupsLoading = false
			}
		},

		buildGroupAvatarUrl(group) {
			return generateOcsUrl(`/apps/olvid/app/groups/${encodeURIComponent(group.id)}/avatar?photoUid=${encodeURIComponent(group.photoUid)}`)
		},
	},
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

	&__enrolled-badge {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		color: var(--color-success);
		font-weight: 600;
		font-size: 0.9rem;
	}

	&__form {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	&__revoke {
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
