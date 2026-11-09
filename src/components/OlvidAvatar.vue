<template>
	<div class="olvid-avatar">
		<NcAvatar
			v-bind="$attrs"
			hideStatus
			:title="title"
			:isNoUser="isNoUser"
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

<script setup>
import { t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import { computed } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'

// other attributes (url, user, displayName…) are forwarded to NcAvatar
defineOptions({ inheritAttrs: false })

const props = defineProps({
	useOlvid: {
		type: Boolean,
		required: true,
	},

	isNoUser: {
		type: Boolean,
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
})

const badgeUrl = computed(() => {
	const file = props.useOlvid && !props.isInactive ? 'olvid.svg' : 'olvid-disabled.svg'
	return generateFilePath('olvid', 'img', file)
})

const title = computed(() => {
	if (props.isNoUser) {
		// groups
		return props.useOlvid
			? t('olvid', 'Olvid discussion enabled')
			: t('olvid', 'Olvid discussion disabled')
	}
	// user
	if (!props.useOlvid) {
		return t('olvid', 'Not enrolled with Olvid')
	}
	return props.isInactive
		? t('olvid', 'Enrolled with Olvid, but inactive')
		: t('olvid', 'Enrolled with Olvid')
})
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
