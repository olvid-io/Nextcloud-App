import axios from '@nextcloud/axios'
import { n, t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import App from './App.vue'
import router from './router.ts'

// set up an interceptor that will unwrap ocs responses data before we start the Vue app
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

const app = createApp(App)
// expose translation helpers to every component (templates and `this.t()` in scripts)
app.mixin({ methods: { t, n } })
app.use(router)
app.mount('#olvid')
