<template>
	<!-- Avatar preview + two picker buttons stacked vertically -->
	<div class="avatar-picker">
		<!-- Preview: pending selection > saved URL > NcAvatar initials fallback -->
		<div class="avatar-picker__preview">
			<img
				v-if="pendingDataUrl || currentUrl"
				:src="pendingDataUrl || currentUrl"
				:style="{ width: size + 'px', height: size + 'px' }"
				class="avatar-picker__img"
				:alt="displayName" />
			<NcAvatar
				v-else
				:display-name="displayName"
				:is-no-user="true"
				:size="size" />
		</div>

		<!-- Hidden <input type="file"> — triggered programmatically by the Local file button -->
		<input
			ref="fileInput"
			type="file"
			accept="image/*"
			class="avatar-picker__file-input"
			@change="handleLocalFile" />

		<!-- Picker buttons -->
		<div class="avatar-picker__actions">
			<NcButton size="small" @click="$refs.fileInput.click()">
				{{ t('olvid', 'Local file') }}
			</NcButton>
			<NcButton size="small" @click="openNextcloudPicker">
				{{ t('olvid', 'From Nextcloud') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { FilePickerClosed, getFilePickerBuilder } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { generateRemoteUrl } from '@nextcloud/router'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

// Maximum side length of the output square in pixels
const MAX_PIXEL_SIZE = 1080

export default {
	name: 'AvatarPicker',
	components: { NcAvatar, NcButton },

	props: {
		/**
		 * Full URL of the currently-saved avatar image, or null/undefined when
		 * no avatar has been set yet.  The NcAvatar initials fallback is shown
		 * when this is falsy and no pending selection exists.
		 */
		currentUrl: {
			type: String,
			default: null,
		},
		/** Name used for NcAvatar's initials fallback. */
		displayName: {
			type: String,
			required: true,
		},
		/** Avatar display size in pixels (both width and height). */
		size: {
			type: Number,
			default: 100,
		},
	},

	emits: ['change'],

	data() {
		return {
			/**
			 * Base64 data URL of the image the user just selected, shown in the
			 * preview while the parent is uploading.  Cleared automatically when
			 * currentUrl changes (i.e. the parent confirmed the upload and updated
			 * the group's photoUid, which changes the URL prop).
			 */
			pendingDataUrl: null,
		}
	},

	watch: {
		// When the parent commits the new avatar (photoUid changes → new currentUrl),
		// drop the pending data URL so we switch to the server-hosted image.
		currentUrl() {
			this.pendingDataUrl = null
		},
	},

	methods: {
		// ── Local file picker ──────────────────────────────────────────────────

		handleLocalFile(event) {
			const file = event.target.files?.[0]
			if (!file) return

			const reader = new FileReader()
			reader.readAsArrayBuffer(file)
			reader.onload = (e) => this.processBuffer(e.target.result)

			// Reset the input value so selecting the same file a second time still
			// fires a 'change' event.
			this.$refs.fileInput.value = ''
		},

		// ── Nextcloud file picker ──────────────────────────────────────────────

		async openNextcloudPicker() {
			try {
				// Open the Nextcloud FilePicker restricted to common image formats.
				// addButton() is required: without it, pick() never resolves.
				const filePath = await getFilePickerBuilder(t('olvid', 'Select an image'))
					.setMultiSelect(false)
					.setMimeTypeFilter(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
					.addButton({
						label: t('olvid', 'Choose'),
						variant: 'primary',
						callback: () => {},
					})
					.build()
					.pick()

				// Fetch the selected file via WebDAV using the current user's DAV root
				const user = getCurrentUser()
				const url = generateRemoteUrl(
					`dav/files/${encodeURIComponent(user.uid)}${filePath}`,
				)
				const res = await axios.get(url, { responseType: 'arraybuffer' })
				this.processBuffer(res.data)
			} catch (e) {
				// FilePickerClosed is not an error — the user just cancelled the dialog
				if (e instanceof FilePickerClosed) return
				console.error('[AvatarPicker] Nextcloud file picker error', e)
			}
		},

		// ── Shared image processing pipeline ──────────────────────────────────

		/**
		 * Convert an ArrayBuffer to a cropped + compressed base64 JPEG data URL
		 * and emit it so the parent can upload it.
		 */
		processBuffer(buffer) {
			// Wrap in a Blob so it can be used as an <img> src via createObjectURL
			const blob = new Blob([buffer])
			const blobUrl = URL.createObjectURL(blob)

			const img = new Image()
			img.src = blobUrl
			img.onload = () => {
				URL.revokeObjectURL(blobUrl)
				const dataUrl = this.cropAndCompress(img)
				// Show locally while the parent uploads in the background
				this.pendingDataUrl = dataUrl
				this.$emit('change', dataUrl)
			}
		},

		/**
		 * Center-square crop, scale to ≤ MAX_PIXEL_SIZE, and encode as JPEG 0.75.
		 *
		 * Matches the Keycloak plugin's resizeImage() exactly:
		 *   squareSize = min(width, height)
		 *   if squareSize > MAX → scale both dims proportionally
		 *   draw with a negative offset to center-crop the longer axis
		 *   output: image/jpeg at quality 0.75
		 */
		cropAndCompress(img) {
			const canvas = document.createElement('canvas')
			let w = img.width
			let h = img.height

			// Determine the output square side (shorter dimension, capped at MAX_PIXEL_SIZE)
			let squareSize = Math.min(w, h)
			if (squareSize > MAX_PIXEL_SIZE) {
				const scale = MAX_PIXEL_SIZE / squareSize
				w *= scale
				h *= scale
				squareSize = MAX_PIXEL_SIZE
			}

			canvas.width = squareSize
			canvas.height = squareSize

			// Negative offset centers the image so the excess on each axis is
			// cropped equally from both sides
			const ctx = canvas.getContext('2d')
			ctx.drawImage(img, -(w - squareSize) / 2, -(h - squareSize) / 2, w, h)

			return canvas.toDataURL('image/jpeg', 0.75)
		},
	},
}
</script>

<style scoped lang="scss">
.avatar-picker {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;

	&__preview {
		border-radius: 50%;
		overflow: hidden;
		flex-shrink: 0;
	}

	&__img {
		display: block;
		border-radius: 50%;
		object-fit: cover;
	}

	// Hidden — triggered programmatically
	&__file-input {
		display: none;
	}

	&__actions {
		display: flex;
		gap: 6px;
		flex-wrap: wrap;
		justify-content: center;
	}
}
</style>
