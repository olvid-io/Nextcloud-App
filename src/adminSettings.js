import axios from '@nextcloud/axios'
import { n, t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'

// same OCS unwrapping as main.js (each entry point has its own axios instance)
axios.interceptors.response.use((response) => {
	if (response.data?.ocs?.data !== undefined) {
		response.data = response.data.ocs.data
	}
	return response
}, (error) => {
	if (error.response?.data?.ocs?.data !== undefined) {
		error.response.data = error.response.data.ocs.data
	}
	return Promise.reject(error)
})

const app = createApp(AdminSettings)
// expose translation helpers to every template
app.config.globalProperties.t = t
app.config.globalProperties.n = n
app.mount('#olvid-admin-settings')
