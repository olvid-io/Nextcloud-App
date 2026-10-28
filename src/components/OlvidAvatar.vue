<template>
	<div class="olvid-avatar">
		<NcAvatar
			v-bind="$attrs"
			hide-status="true"
			:title="title"
			:is-no-user="isNoUser"
			:size="size" />
		<img
			v-if="useOlvid"
			:src="badgeUrl"
			:width="Math.round(size / 3)"
			:height="Math.round(size / 3)"
			:title="title"
			aria-hidden="true"
			class="olvid-avatar__badge"
			alt="olvid badge">
	</div>
</template>

<script>
import { generateFilePath } from '@nextcloud/router'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

export default {
	name: 'OlvidAvatar',
	components: { NcAvatar },

	inheritAttrs: false,

	props: {
		useOlvid: {
			type: Boolean,
			required: true,
		},
		isNoUser: {
			type: Boolean,
			required: true,
			default: false,
		},
		isInactive: {
			type: Boolean,
			default: false,
		},
		size: {
			type: Number,
			default: 32,
		},
	},

	computed: {
		badgeUrl() {
			// const file = this.useOlvid && !this.isInactive ? 'olvid-enabled.svg' : 'olvid-disabled.svg'
			const file = this.useOlvid && !this.isInactive ? 'olvid.svg' : 'olvid-disabled.svg'
			return generateFilePath('olvid', 'img', file)
		},
		title() {
			if (this.isNoUser) {
			// groups
				if (this.useOlvid) {
					return this.t('olvid', 'Olvid discussion enabled')
				} else {
					return this.t('olvid', 'Olvid discussion disabled')
				}
			} else {
				// user
				if (this.useOlvid) {
					if (this.isInactive) {
						return this.t('olvid', 'Enrolled with Olvid, but inactive')
					} else {
						return this.t('olvid', 'Enrolled with Olvid')
					}
				} else {
					return this.t('olvid', 'Not enrolled with Olvid')
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.olvid-avatar {
	position: relative;
	display: inline-flex;
	flex-shrink: 0;

	&__badge {
		position: absolute;
		top: -3px;
		inset-inline-end: -3px;
		border-radius: 3px;
	}
}
</style>
