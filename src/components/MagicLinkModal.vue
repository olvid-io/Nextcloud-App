<template>
	<NcDialog
		:name="t('olvid', 'Magic Link — {name}', { name: user.displayName })"
		:open="true"
		@update:open="$emit('close')">
		<div class="magic-link-modal">
			<p class="magic-link-modal__hint">
				{{ t('olvid', 'Scan with Olvid or share the link. Valid for 5 minutes.') }}
			</p>

			<div v-if="qrDataUrl" class="magic-link-modal__qr">
				<img
					:src="qrDataUrl"
					:alt="t('olvid', 'Olvid configuration QR code')"
					class="magic-link-modal__qr-image" />
			</div>
			<NcLoadingIcon v-else :size="44" />
		</div>

		<template #actions>
			<NcButton @click="openWithOlvid">
				{{ t('olvid', 'Open with Olvid') }}
			</NcButton>
			<NcButton @click="copyLink">
				{{ copied ? t('olvid', 'Copied!') : t('olvid', 'Copy link') }}
			</NcButton>
			<NcButton type="primary" @click="$emit('close')">
				{{ t('olvid', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import QRCode from 'qrcode'

export default {
	name: 'MagicLinkModal',
	components: { NcDialog, NcButton, NcLoadingIcon },

	props: {
		user: {
			type: Object,
			required: true,
		},
		configurationUrl: {
			type: String,
			required: true,
		},
	},

	emits: ['close'],

	data() {
		return {
			qrDataUrl: null,
			copied: false,
		}
	},

	watch: {
		async configurationUrl(url) {
			await this.generateQr(url)
		},
	},

	async mounted() {
		await this.generateQr(this.configurationUrl)
	},

	methods: {
		async generateQr(url) {
			if (!url) return
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

		openWithOlvid() {
			if (this.configurationUrl) {
				window.open(this.configurationUrl.replace(/^https?:\/\//, 'olvid://'))
			}
		},

		async copyLink() {
			try {
				await navigator.clipboard.writeText(this.configurationUrl)
				this.copied = true
				setTimeout(() => { this.copied = false }, 2000)
			} catch (e) {
				console.error('Could not copy to clipboard', e)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.magic-link-modal {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 16px;
	padding: 8px 0;

	&__hint {
		margin: 0;
		color: var(--color-text-maxcontrast);
		text-align: center;
	}

	&__qr {
		padding: 12px;
		background: #ffffff;
		border-radius: var(--border-radius);
		border: 1px solid var(--color-border);
	}

	&__qr-image {
		display: block;
		width: 244px;
		height: 244px;
	}
}
</style>
