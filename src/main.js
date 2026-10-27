import Vue from 'vue'
import App from './App.vue'
import router from './router.ts'
import axios from '@nextcloud/axios'
// import toast message style
import '@nextcloud/dialogs/style.css'

// set up an interceptor that will unwrap ocs responses data before we start the Vue app
axios.interceptors.response.use(response => {
	if (response.data?.ocs?.data !== undefined) {
		response.data = response.data.ocs.data
	}
	return response
}, error => {
	if (error.response?.data?.ocs?.data !== undefined) {
		error.response.data = error.response.data.ocs.data
	}
	return Promise.reject(error)
})

Vue.mixin({ methods: { t, n } })
new Vue({ router, render: h => h(App) }).$mount('#content')
