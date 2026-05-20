<template>
	<NcAppContent>
		<div id="olvid">
			<NcButton v-if="!magicLink" :disabled="loading" @click="fetchMagicLink">
				Get my Olvid magic link
			</NcButton>

			<p v-if="error" style="color: red;">
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
	</NcAppContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'App',
	components: { NcAppContent, NcButton },
	data() {
		return {
			loading: true,
			magicLink: null,
			error: null,
			olvidIdentityUploaded: false,
		}
	},
	async mounted() {
		try {
			const res = await axios.get(generateOcsUrl('/apps/olvid/app/status'))
			this.olvidIdentityUploaded = res.data.olvidIdentityUploaded
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
	},
}
</script>

<style scoped lang="scss">
#olvid {
	display: flex;
	justify-content: center;
	margin: 16px;
}
</style>
