<template>
	<NcAppContent>
		<div id="olvid">
			<!-- ── Enrollment section ─────────────────────────────────── -->
			<div class="olvid-enrollment">
				<!-- View 1: identity enrolled, no pending re-enrolment -->
				<template v-if="olvidIdentityUploaded && !magicLink">
					<div class="olvid-enrolled-state">
						<span class="olvid-enrolled-badge">✓ Olvid identity enrolled</span>
						<NcButton :disabled="loading" @click="revokeIdentity">
							Revoke my identity
						</NcButton>
					</div>
				</template>

				<!-- View 2: no identity yet, link not generated -->
				<template v-else-if="!magicLink">
					<NcButton :disabled="loading" @click="fetchMagicLink">
						Enroll with Olvid
					</NcButton>
				</template>

				<!-- View 3: magic link ready → show QR code -->
				<template v-else>
					<div class="olvid-qr-card">
						<img v-if="qrDataUrl"
							:src="qrDataUrl"
							class="olvid-qr-image"
							alt="Olvid configuration QR code" />
						<p class="olvid-qr-hint">
							Scan with Olvid to enroll
						</p>
						<div class="olvid-qr-actions">
							<NcButton @click="openWithOlvid">
								Open with Olvid
							</NcButton>
							<NcButton @click="copyLink">
								{{ copied ? 'Copied!' : 'Copy link' }}
							</NcButton>
						</div>
					</div>
				</template>

				<p v-if="error" class="olvid-error">{{ error }}</p>
			</div>

			<!-- ── Profile form ───────────────────────────────────────── -->
			<div class="olvid-profile">
				<h2>My Olvid profile</h2>
				<NcTextField :value.sync="form.firstname" label="First name" />
				<NcTextField :value.sync="form.lastname" label="Last name" />
				<NcTextField :value.sync="form.position" label="Position" />
				<NcTextField :value.sync="form.company" label="Company" />
				<p v-if="saveError" class="olvid-error">{{ saveError }}</p>
				<p v-if="saveSuccess" class="olvid-success">Profile saved.</p>
				<NcButton :disabled="saving" @click="saveProfile">
					Save
				</NcButton>
			</div>

		</div>
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import QRCode from 'qrcode'

export default {
	name: 'ProfileView',
	components: { NcAppContent, NcButton, NcTextField },
	data() {
		return {
			loading: true,
			magicLink: null,
			qrDataUrl: null,
			copied: false,
			error: null,
			olvidIdentityUploaded: false,
			form: {
				firstname: '',
				lastname: '',
				position: '',
				company: '',
			},
			saving: false,
			saveError: null,
			saveSuccess: false,
		}
	},
	watch: {
		async magicLink(url) {
			if (!url) {
				this.qrDataUrl = null
				return
			}
			try {
				this.qrDataUrl = await QRCode.toDataURL(url, {
					width: 244,
					margin: 2,
					errorCorrectionLevel: 'M',
					color: { dark: '#000000', light: '#ffffff' },
				})
			} catch (e) {
				console.error('QR generation failed', e)
			}
		},
	},
	async mounted() {
		try {
			const [statusRes, meRes] = await Promise.all([
				axios.get(generateOcsUrl('/apps/olvid/app/status')),
				axios.get(generateOcsUrl('/apps/olvid/app/me')),
			])
			this.olvidIdentityUploaded = statusRes.data.olvidIdentityUploaded
			this.form = {
				firstname: meRes.data.firstname ?? '',
				lastname: meRes.data.lastname ?? '',
				position: meRes.data.position ?? '',
				company: meRes.data.company ?? '',
			}
		} catch (e) {
			console.error('Could not fetch Olvid status', e)
		} finally {
			this.loading = false
		}
	},
	methods: {
		async fetchMagicLink() {
			this.loading = true
			this.error = null
			this.magicLink = null
			try {
				const response = await axios.get(generateOcsUrl('/apps/olvid/app/getMagicLink'))
				this.magicLink = response.data.configurationUrl
			} catch (e) {
				this.error = 'Could not generate magic link: ' + (e.response?.data?.error ?? e.message)
			} finally {
				this.loading = false
			}
		},
		async revokeIdentity() {
			this.loading = true
			this.error = null
			try {
				await axios.get(generateOcsUrl('/apps/olvid/app/revokeIdentity'))
				this.olvidIdentityUploaded = false
				// Go directly to QR view for re-enrollment
				await this.fetchMagicLink()
			} catch (e) {
				this.error = 'Could not revoke identity: ' + (e.response?.data?.error ?? e.message)
				this.loading = false
			}
		},
		openWithOlvid() {
			if (this.magicLink) {
				window.open(this.magicLink.replace(/^https?:\/\//, 'olvid://'))
			}
		},
		async copyLink() {
			if (!this.magicLink) return
			try {
				await navigator.clipboard.writeText(this.magicLink)
				this.copied = true
				setTimeout(() => { this.copied = false }, 2000)
			} catch (e) {
				this.error = 'Could not copy to clipboard'
			}
		},
		async saveProfile() {
			this.saving = true
			this.saveError = null
			this.saveSuccess = false
			try {
				await axios.put(generateOcsUrl('/apps/olvid/app/me'), this.form)
				this.saveSuccess = true
			} catch (e) {
				this.saveError = 'Could not save profile: ' + (e.response?.data?.error ?? e.message)
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped lang="scss">
#olvid {
	display: flex;
	flex-direction: column;
	gap: 32px;
	max-width: 480px;
	margin: 32px auto;
	padding: 0 16px;
}

/* ── Enrolled state ──────────────────────────────────────────── */
.olvid-enrolled-state {
	display: flex;
	flex-direction: column;
	gap: 12px;
	align-items: flex-start;
}

.olvid-enrolled-badge {
	font-weight: 600;
	color: var(--color-success);
	font-size: 1rem;
}

/* ── QR card ─────────────────────────────────────────────────── */
.olvid-qr-card {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 16px;
	padding: 24px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.olvid-qr-image {
	width: 244px;
	height: 244px;
	border-radius: 4px;
}

.olvid-qr-hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.9rem;
}

.olvid-qr-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	justify-content: center;
}

/* ── Profile form ────────────────────────────────────────────── */
.olvid-profile {
	display: flex;
	flex-direction: column;
	gap: 12px;

	h2 {
		margin: 0 0 4px;
	}
}

/* ── Feedback ────────────────────────────────────────────────── */
.olvid-error {
	color: var(--color-error);
	margin: 0;
}

.olvid-success {
	color: var(--color-success);
	margin: 0;
}
</style>
