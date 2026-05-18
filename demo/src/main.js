import { createApp } from 'vue'
import { installRepoLocalModule } from '@kennofizet/repo-local-frontend'
import App from './App.vue'

const app = createApp(App)

installRepoLocalModule(app, {
  apiBaseUrl: '/api/repo-local',
  routerBase: '/',
})

app.mount('#app')
