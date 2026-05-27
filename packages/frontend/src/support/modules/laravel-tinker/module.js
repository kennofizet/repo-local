import { useTinkerTestUsers } from '../../../composables/useTinkerTestUsers.js'
import TinkerSupportPanel from '../../../components/TinkerSupportPanel.vue'

export const LaravelTinkerSupportModule = {
  id: 'laravel.tinker',
  label: 'Laravel · Tinker',
  panelComponent: TinkerSupportPanel,
  useState(projectIdRef) {
    const {
      state: tinkerState,
      users: tinkerUsers,
      defaultUser: tinkerDefaultUser,
      displayLabel: tinkerDisplayLabel,
      setResolveCode,
      setQueryDraft,
      setPanelOpen,
      setInnerTab,
      addUser,
      removeUser,
      setDefault,
    } = useTinkerTestUsers(projectIdRef)

    return {
      state: tinkerState,
      users: tinkerUsers,
      defaultUser: tinkerDefaultUser,
      displayLabel: tinkerDisplayLabel,
      setResolveCode,
      setQueryDraft,
      setPanelOpen,
      setInnerTab,
      addUser,
      removeUser,
      setDefault,
    }
  },
  isSupportedByProject(project) {
    const supports = project?.supports
    if (!Array.isArray(supports)) return false
    return supports.some((s) => (typeof s === 'string' ? s === 'laravel.tinker' : s?.id === 'laravel.tinker'))
  },
}

