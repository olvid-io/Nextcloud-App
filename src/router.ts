import { generateUrl } from '@nextcloud/router'
import Vue from 'vue'
import Router from 'vue-router'
import GroupsView from './views/GroupsView.vue'
import ProfileView from './views/ProfileView.vue'
import UsersView from './views/UsersView.vue'

Vue.use(Router)

export default new Router({
	mode: 'history',
	base: generateUrl('/apps/olvid'),
	routes: [
		{ path: '/', redirect: '/profile' },
		{ path: '/profile', name: 'profile', component: ProfileView },
		{ path: '/groups', name: 'groups', component: GroupsView },
		{ path: '/groups/:groupId', name: 'group-detail', component: GroupsView, props: true },
		{ path: '/users', name: 'users', component: UsersView },
	],
})
