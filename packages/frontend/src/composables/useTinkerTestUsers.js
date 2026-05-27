import { computed, ref, watch } from 'vue'

const STORAGE_PREFIX = 'repo-local:tinker:v1:'

const DEFAULT_RESOLVE_CODE = `$id = 1;
$user = \\App\\Models\\User::queryForSystemJob()->find($id);
return $user;`

function storageKey(projectId) {
  return `${STORAGE_PREFIX}${projectId || '_global'}`
}

function loadState(projectId) {
  try {
    const raw = localStorage.getItem(storageKey(projectId))
    if (!raw) {
      return createEmptyState()
    }
    const parsed = JSON.parse(raw)
    return {
      users: Array.isArray(parsed.users) ? parsed.users : [],
      defaultEntryId: parsed.defaultEntryId || null,
      resolveCode: typeof parsed.resolveCode === 'string' ? parsed.resolveCode : DEFAULT_RESOLVE_CODE,
      queryDraft: typeof parsed.queryDraft === 'string' ? parsed.queryDraft : '',
      panelOpen: parsed.panelOpen === true,
      innerTab: parsed.innerTab === 'usercase' ? 'usercase' : 'query',
    }
  } catch {
    return createEmptyState()
  }
}

function createEmptyState() {
  return {
    users: [],
    defaultEntryId: null,
    resolveCode: DEFAULT_RESOLVE_CODE,
    queryDraft: '',
    panelOpen: false,
    innerTab: 'query',
  }
}

function persist(projectId, state) {
  localStorage.setItem(storageKey(projectId), JSON.stringify(state))
}

function newEntryId() {
  return `u_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
}

export function useTinkerTestUsers(projectIdRef) {
  const state = ref(createEmptyState())

  function syncFromStorage() {
    const pid = projectIdRef.value
    state.value = loadState(pid)
  }

  watch(projectIdRef, syncFromStorage, { immediate: true })

  function save() {
    persist(projectIdRef.value, state.value)
  }

  const users = computed(() => state.value.users)

  const defaultUser = computed(() => {
    const id = state.value.defaultEntryId
    if (!id) return null
    return state.value.users.find((u) => u.id === id) || null
  })

  const defaultLaravelUserId = computed(() => {
    const u = defaultUser.value
    return u?.laravelUserId != null ? Number(u.laravelUserId) : null
  })

  function displayLabel(entry) {
    const name = (entry?.name || '').trim()
    if (name) return `${name} (#${entry.laravelUserId})`
    return `#${entry.laravelUserId}`
  }

  function addUser({ laravelUserId, name = '', resolveCode = '' }) {
    const uid = Number(laravelUserId)
    const existing = state.value.users.find((u) => u.laravelUserId === uid)
    if (existing) {
      updateUser(existing.id, {
        name: String(name || '').trim() || existing.name,
        resolveCode: resolveCode || existing.resolveCode,
      })
      return existing
    }

    const id = newEntryId()
    const entry = {
      id,
      laravelUserId: uid,
      name: String(name || '').trim(),
      resolveCode: String(resolveCode || '').trim(),
      createdAt: Date.now(),
    }
    state.value.users = [...state.value.users, entry]
    if (!state.value.defaultEntryId) {
      state.value.defaultEntryId = id
    }
    save()
    return entry
  }

  function updateUser(entryId, patch) {
    state.value.users = state.value.users.map((u) =>
      u.id === entryId
        ? {
            ...u,
            ...patch,
            laravelUserId:
              patch.laravelUserId != null ? Number(patch.laravelUserId) : u.laravelUserId,
            name: patch.name != null ? String(patch.name).trim() : u.name,
            resolveCode: patch.resolveCode != null ? String(patch.resolveCode).trim() : u.resolveCode,
          }
        : u,
    )
    save()
  }

  function removeUser(entryId) {
    state.value.users = state.value.users.filter((u) => u.id !== entryId)
    if (state.value.defaultEntryId === entryId) {
      state.value.defaultEntryId = state.value.users[0]?.id || null
    }
    save()
  }

  function setDefault(entryId) {
    if (!state.value.users.some((u) => u.id === entryId)) return
    state.value.defaultEntryId = entryId
    save()
  }

  function setResolveCode(code) {
    state.value.resolveCode = code
    save()
  }

  function setQueryDraft(code) {
    state.value.queryDraft = code
    save()
  }

  function setPanelOpen(open) {
    state.value.panelOpen = open
    save()
  }

  function setInnerTab(tab) {
    state.value.innerTab = tab === 'usercase' ? 'usercase' : 'query'
    save()
  }

  return {
    state,
    users,
    defaultUser,
    defaultLaravelUserId,
    displayLabel,
    syncFromStorage,
    addUser,
    updateUser,
    removeUser,
    setDefault,
    setResolveCode,
    setQueryDraft,
    setPanelOpen,
    setInnerTab,
    DEFAULT_RESOLVE_CODE,
  }
}
