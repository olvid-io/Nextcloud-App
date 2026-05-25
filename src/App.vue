<template>
	<NcAppContent>
		<div id="olvid">
			<div class="olvid-actions">
				<NcButton v-if="!magicLink" :disabled="loading" @click="fetchMagicLink">
					Get my Olvid magic link
				</NcButton>

				<p v-if="error" class="olvid-error">
					{{ error }}
				</p>

				<NcButton v-if="magicLink" @click="openMagicLink">
					See magic link
				</NcButton>

				<NcButton v-if="magicLink" @click="openMagicLinkWithOlvid">
					Open magic link with Olvid
				</NcButton>

				<NcButton v-if="olvidIdentityUploaded" @click="revokeIdentity">
					Revoke current Olvid Identity
				</NcButton>
			</div>

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

export default {
	name: 'App',
	components: { NcAppContent, NcButton, NcTextField },
	data() {
		return {
			loading: true,
			magicLink: null,
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
				const url = generateOcsUrl('/apps/olvid/app/getMagicLink')
				const response = await axios.get(url)
				this.magicLink = response.data.configurationUrl
			} catch (e) {
				this.error = 'Could not generate magic link: ' + (e.response?.data?.error ?? e.message)
			} finally {
				this.loading = false
			}
		},
		async openMagicLink() {
			if (this.magicLink) {
				window.open(this.magicLink, '_blank')
			}
		},
		async openMagicLinkWithOlvid() {
			if (this.magicLink) {
				window.open(this.magicLink.replace('http://', 'olvid://').replace('https://', 'olvid://'))
			}
		},
		async revokeIdentity() {
			const url = generateOcsUrl('/apps/olvid/app/revokeIdentity')
			await axios.get(url)
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
	gap: 24px;
	max-width: 480px;
	margin: 24px auto;
}

.olvid-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
}

.olvid-profile {
	display: flex;
	flex-direction: column;
	gap: 12px;

	h2 {
		margin: 0 0 4px;
	}
}

.olvid-error {
	color: var(--color-error);
	margin: 0;
}

.olvid-success {
	color: var(--color-success);
	margin: 0;
}
</style>
