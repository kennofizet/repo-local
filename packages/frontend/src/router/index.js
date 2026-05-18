import { createRouter, createWebHistory } from 'vue-router'
import RepoLocalApp from '../components/RepoLocalApp.vue'

export function createRepoLocalRouter(base = '/') {
  return createRouter({
    history: createWebHistory(base),
    routes: [
      {
        path: '/',
        component: RepoLocalApp,
      },
      {
        path: '/p/:projectId/:tab?',
        component: RepoLocalApp,
        props: true,
      },
    ],
  })
}
