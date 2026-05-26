import Vue from 'vue'
import App from './App.vue'
import router from './router.ts'

Vue.mixin({ methods: { t, n } })

new Vue({ router, render: h => h(App) }).$mount('#content')
