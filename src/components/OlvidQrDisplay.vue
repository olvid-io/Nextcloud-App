<template>
	<div class="olvid-qr-display">

		<!-- Mobile path: QR scan ─────────────────────────────────── -->
		<div class="olvid-qr-display__section">
			<p class="olvid-qr-display__section-title">
				{{ t('olvid', '📱 On your phone') }}
			</p>
			<p class="olvid-qr-display__instruction">
				{{ t('olvid', 'Open the Olvid app, then scan this QR code to link your account:') }}
			</p>
			<div class="olvid-qr-display__qr-wrapper">
				<img
					v-if="qrDataUrl"
					:src="qrDataUrl"
					:alt="t('olvid', 'Olvid configuration QR code')"
					class="olvid-qr-display__image" />
				<NcLoadingIcon v-else :size="44" />
			</div>
		</div>

		<!-- Divider ──────────────────────────────────────────────── -->
		<div class="olvid-qr-display__divider">
			<span>{{ t('olvid', 'or') }}</span>
		</div>

		<!-- Desktop path: open link ──────────────────────────────── -->
		<div class="olvid-qr-display__section">
			<p class="olvid-qr-display__section-title">
				{{ t('olvid', '💻 On this computer') }}
			</p>
			<p class="olvid-qr-display__instruction">
				{{ t('olvid', 'If Olvid is installed on this computer, click the button below to open it directly:') }}
			</p>
			<div class="olvid-qr-display__desktop-actions">
				<NcButton type="primary" @click="openWithOlvid">
					{{ t('olvid', 'Open with Olvid') }}
				</NcButton>
				<NcButton @click="copyLink">
					{{ copied ? t('olvid', 'Link copied!') : t('olvid', 'Copy link') }}
				</NcButton>
			</div>
			<p class="olvid-qr-display__copy-hint">
				{{ t('olvid', 'You can also copy the link and paste it in Olvid manually.') }}
			</p>
		</div>

	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import QRCode from 'qrcode'

export default {
	name: 'OlvidQrDisplay',
	components: { NcButton, NcLoadingIcon },

	props: {
		configurationUrl: {
			type: String,
			required: true,
		},
	},

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
				window.open('olvid://' + this.configurationUrl)
			}
		},

		async copyLink() {
			try {
				await navigator.clipboard.writeText(this.configurationUrl)
				this.copied = true
				setTimeout(() => { this.copied = false }, 2500)
			} catch (e) {
				console.error('Could not copy to clipboard', e)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.olvid-qr-display {
	display: flex;
	flex-direction: column;
	gap: 24px;
	width: 100%;

	// ── Shared section layout ──────────────────────────────────────────────
	&__section {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 10px;
		text-align: center;
	}

	&__section-title {
		margin: 0;
		font-weight: 600;
		font-size: 1rem;
	}

	&__instruction {
		margin: 0;
		color: var(--color-text-maxcontrast);
		line-height: 1.5;
	}

	// ── QR code ────────────────────────────────────────────────────────────
	&__qr-wrapper {
		display: flex;
		justify-content: center;
		align-items: center;
		min-height: 268px; // prevents layout shift while QR generates
	}

	&__image {
		display: block;
		width: 244px;
		height: 244px;
		padding: 12px;
		background: #ffffff;
		border-radius: var(--border-radius);
		border: 1px solid var(--color-border);
		box-sizing: content-box;
	}

	// ── Or divider ─────────────────────────────────────────────────────────
	&__divider {
		display: flex;
		align-items: center;
		gap: 12px;
		color: var(--color-text-maxcontrast);
		font-size: 0.9rem;

		&::before,
		&::after {
			content: '';
			flex: 1;
			border-top: 1px solid var(--color-border);
		}
	}

	// ── Desktop actions ────────────────────────────────────────────────────
	&__desktop-actions {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
		justify-content: center;
	}

	&__copy-hint {
		margin: 0;
		font-size: 0.85rem;
		color: var(--color-text-maxcontrast);
	}
}
</style>
