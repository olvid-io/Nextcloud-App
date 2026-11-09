import { generateUrl } from '@nextcloud/router'
import { createRouter, createWebHistory } from 'vue-router'
import GroupsView from './views/GroupsView.vue'
import ProfileView from './views/ProfileView.vue'
import StatisticsView from './views/StatisticsView.vue'
import UsersView from './views/UsersView.vue'

export default createRouter({
	history: createWebHistory(generateUrl('/apps/olvid')),
	routes: [
		{ path: '/', redirect: '/profile' },
		{ path: '/profile', name: 'profile', component: ProfileView },
		{ path: '/groups', name: 'groups', component: GroupsView },
		{ path: '/groups/:groupId', name: 'group-detail', component: GroupsView },
		{ path: '/users', name: 'users', component: UsersView },
		{ path: '/users/:userId', name: 'user-detail', component: UsersView },
		{ path: '/statistics', name: 'statistics', component: StatisticsView },
	],
})
