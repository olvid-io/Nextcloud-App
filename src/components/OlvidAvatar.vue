<template>
	<div class="olvid-avatar">
		<NcAvatar
			v-bind="$attrs"
			hide-status="true"
			:size="size"
			v-on="$listeners" />
		<img
			v-if="useOlvid"
			:src="badgeUrl"
			:width="Math.round(size / 3)"
			:height="Math.round(size / 3)"
			:title="useOlvid ? t('olvid', 'Enrolled with Olvid') : t('olvid', 'Not enrolled with Olvid')"
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
		size: {
			type: Number,
			default: 32,
		},
	},

	computed: {
		badgeUrl() {
			// const file = this.useOlvid ? 'olvid-enabled.svg' : 'olvid-disabled.svg'
			return generateFilePath('olvid', 'img', 'olvid.svg')
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
