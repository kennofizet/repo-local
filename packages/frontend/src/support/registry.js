import { LaravelTinkerSupportModule } from './modules/laravel-tinker/module.js'

const modules = [LaravelTinkerSupportModule]

export function listSupportModules() {
  return modules.slice()
}

export function pickSupportModuleForProject(project) {
  return modules.find((m) => m.isSupportedByProject(project)) || null
}

