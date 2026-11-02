import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		rules: {
			'jsdoc/require-jsdoc': 'off',
			'vue/first-attribute-linebreak': 'off',
			// console.error is used on purpose to log failed API calls
			'no-console': ['error', { allow: ['error'] }],
		},
	},
]
