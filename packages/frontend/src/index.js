import { createRepoLocalApi } from './api'
import RepoLocalApp from './components/RepoLocalApp.vue'
import { createRepoLocalRouter } from './router'

/**
 * Install Repo Local browser module.
 * @param {import('vue').App} app
 * @param {{ apiBaseUrl?: string, routerBase?: string }} options
 */
export function installRepoLocalModule(app, options = {}) {
  const api = createRepoLocalApi(options.apiBaseUrl || '/api/repo-local')
  app.provide('repoLocalApi', api)

  const router = createRepoLocalRouter(options.routerBase || '/')
  app.use(router)
  app.component('RepoLocalApp', RepoLocalApp)
}

export { createRepoLocalApi, RepoLocalApp, createRepoLocalRouter }

export default {
  install: installRepoLocalModule,
}
