<template>
  <div class="repo-local" :class="{ loading: bootLoading }">
    <header class="rl-header">
      <div class="rl-brand">
        <span class="rl-logo" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <div class="rl-brand-text">
          <strong>Repo Local</strong>
          <span v-if="workspace" class="rl-root">{{ workspace.root_name }}</span>
        </div>
      </div>
      <div v-if="activeProject" class="rl-project-head">
        <span class="rl-repo-name">{{ activeProject.name }}</span>
        <span v-if="activeProject.branch" class="rl-branch">
          <span class="dot" />{{ activeProject.branch }}
        </span>
        <span v-if="!activeProject?.is_git" class="rl-badge">local only</span>
      </div>
    </header>

    <div class="rl-body">
      <aside class="rl-sidebar">
        <div class="rl-panel-head">
          <span>Workspace</span>
          <span class="count">{{ filteredProjects.length }}<span v-if="filteredProjects.length !== projects.length" class="of">/{{ projects.length }}</span></span>
        </div>
        <div class="rl-filter-bar seg">
          <button type="button" :class="{ active: projectFilter === 'all' }" @click="projectFilter = 'all'">All</button>
          <button type="button" :class="{ active: projectFilter === 'changed' }" @click="projectFilter = 'changed'" :title="`${changedCount} repo${changedCount === 1 ? '' : 's'} with uncommitted changes`">
            Changed<span v-if="changedCount" class="seg-count">{{ changedCount }}</span>
          </button>
          <button type="button" :class="{ active: projectFilter === 'clean' }" @click="projectFilter = 'clean'">Clean</button>
        </div>
        <div class="rl-scroll">
          <div v-if="bootError" class="rl-error">{{ bootError }}</div>
          <div v-else-if="!filteredProjects.length" class="rl-placeholder small">No repos match this filter.</div>
          <ul v-else class="rl-project-list">
            <li
              v-for="p in filteredProjects"
              :key="p.id"
              :class="{ active: p.id === activeProjectId }"
            >
              <button type="button" @click="selectProject(p)">
                <span class="name">
                  <span
                    v-if="p.has_changes"
                    class="rl-change-dot"
                    title="Has uncommitted changes"
                    aria-label="Has uncommitted changes"
                  />
                  {{ p.name }}
                </span>
                <span class="path">{{ p.path || '.' }}</span>
                <span v-if="p.branch" class="branch">{{ p.branch }}</span>
              </button>
            </li>
          </ul>
        </div>
      </aside>

      <section v-if="!activeProjectId" class="rl-center-empty">
        <div class="empty-card">
          <p>Select a project to browse files, history, and local changes.</p>
        </div>
      </section>

      <div v-else-if="activeProjectId && !activeProject" class="rl-center-empty">
        <div class="empty-card"><p>Loading project…</p></div>
      </div>

      <div v-else-if="activeProject" class="rl-workspace">
        <nav class="rl-tabs">
          <button
            v-for="t in tabs"
            :key="t.id"
            type="button"
            :class="{ active: tab === t.id }"
            @click="setTab(t.id)"
          >
            <span class="tab-icon" v-html="t.icon" />
            {{ t.label }}
            <span v-if="t.id === 'changes' && changeCount" class="tab-count">{{ changeCount }}</span>
          </button>
        </nav>

        <div class="rl-workspace-body">
          <!-- CODE -->
          <div v-if="tab === 'code'" class="rl-code-layout">
            <aside class="rl-tree-panel">
              <div class="rl-panel-head compact">
                <span>Explorer</span>
              </div>
              <div class="rl-scroll">
                <FileTree
                  ref="fileTreeRef"
                  :project-id="activeProjectId"
                  :selected-path="selectedFile"
                  @select-file="openFile"
                />
              </div>
            </aside>
            <article class="rl-preview-panel">
              <div v-if="!selectedFile" class="rl-center-empty inner">
                <div class="empty-card small">
                  <p>Select a file from the tree</p>
                </div>
              </div>
              <template v-else>
                <div class="rl-preview-head">
                  <code class="path">{{ selectedFile }}</code>
                  <span v-if="fileMeta.size != null" class="meta">{{ formatBytes(fileMeta.size) }}</span>
                </div>
                <div class="rl-scroll preview-scroll">
                  <div v-if="fileLoading" class="rl-muted">Loading…</div>
                  <div v-else-if="fileMeta.binary" class="rl-placeholder">{{ fileMeta.message }}</div>
                  <pre v-else class="rl-code"><code>{{ fileContent }}</code></pre>
                </div>
              </template>
            </article>
          </div>

          <!-- COMMITS -->
          <div v-else-if="tab === 'commits'" class="rl-split-layout">
            <aside class="rl-list-panel">
              <div class="rl-panel-head compact"><span>History</span></div>
              <div class="rl-scroll">
                <div v-if="!activeProject?.is_git" class="rl-placeholder">Not a git repository.</div>
                <div v-else-if="commitsLoading" class="rl-muted">Loading…</div>
                <div v-else-if="commitError && !commits.length" class="rl-error">{{ commitError }}</div>
                <div v-else-if="commits.length === 0" class="rl-placeholder">No commits yet.</div>
                <ul v-else class="rl-commit-list">
                  <li
                    v-for="c in commits"
                    :key="c.sha"
                    :class="{ active: selectedCommit?.sha === c.sha }"
                  >
                    <button type="button" @click="selectCommit(c)">
                      <span class="sha">{{ c.short_sha }}</span>
                      <span class="msg">{{ c.message }}</span>
                      <span class="meta">{{ c.author }} · {{ formatDate(c.date) }}</span>
                    </button>
                  </li>
                </ul>
              </div>
            </aside>
            <div class="rl-detail-panel">
              <div v-if="!selectedCommit" class="rl-center-empty inner">
                <div class="empty-card small"><p>Pick a commit</p></div>
              </div>
              <template v-else>
                <div class="rl-detail-head">
                  <h3>{{ selectedCommit.message }}</h3>
                  <p class="sub">{{ selectedCommit.author }} · {{ formatDate(selectedCommit.date) }}</p>
                </div>
                <div class="rl-detail-split">
                  <div class="rl-sublist">
                    <div class="rl-panel-head compact"><span>Files</span></div>
                    <div class="rl-scroll">
                      <ul class="rl-mini-list">
                        <li
                          v-for="f in commitFiles"
                          :key="f.path"
                          :class="{ active: activeCommitFile === f.path }"
                        >
                          <button type="button" @click="viewCommitFile(f)">
                            <span class="st">{{ f.status }}</span>
                            <span class="path">{{ f.path }}</span>
                          </button>
                        </li>
                      </ul>
                    </div>
                  </div>
                  <div class="rl-diff-wrap">
                    <div v-if="commitError" class="rl-error">{{ commitError }}</div>
                    <div class="rl-scroll">
                      <DiffViewer v-if="commitPatch" :patch="commitPatch" />
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- CHANGES -->
          <div v-else-if="tab === 'changes'" class="rl-split-layout">
            <aside class="rl-list-panel">
              <div class="rl-panel-head compact">
                <span>Changes</span>
                <div class="seg">
                  <button type="button" :class="{ active: diffMode === 'unstaged' }" @click="diffMode = 'unstaged'">Work</button>
                  <button type="button" :class="{ active: diffMode === 'staged' }" @click="diffMode = 'staged'">Staged</button>
                </div>
              </div>
              <div class="rl-scroll">
                <div v-if="!activeProject?.is_git" class="rl-placeholder">Not a git repository.</div>
                <div v-else-if="changesLoading" class="rl-muted">Loading…</div>
                <ul v-else class="rl-mini-list">
                  <li
                    v-for="f in changeFiles"
                    :key="f.path"
                    :class="{ active: selectedChangePath === f.path }"
                  >
                    <button type="button" @click="viewChangeFile(f)">
                      <span class="st">{{ f.worktree_status || f.index_status }}</span>
                      <span class="path">{{ f.path }}</span>
                    </button>
                  </li>
                </ul>
              </div>
            </aside>
            <div class="rl-detail-panel">
              <div v-if="!selectedChangePath" class="rl-center-empty inner">
                <div class="empty-card small"><p>Select a changed file</p></div>
              </div>
              <template v-else>
                <div class="rl-preview-head">
                  <code class="path">{{ selectedChangePath }}</code>
                  <span
                    v-if="activeChangeMode"
                    class="rl-mode-badge"
                    :class="{ alt: activeChangeMode !== diffMode }"
                    :title="activeChangeMode !== diffMode ? `No ${diffMode} changes for this file — showing ${activeChangeMode}` : ''"
                  >{{ activeChangeMode === 'staged' ? 'Staged' : 'Work' }}</span>
                </div>
                <div class="rl-scroll">
                  <DiffViewer v-if="changePatch" :patch="changePatch" />
                  <div v-else class="rl-placeholder">No diff available for this file.</div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed, inject, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DiffViewer from './DiffViewer.vue'
import FileTree from './FileTree.vue'
import { formatBytes, formatDate } from '../utils/format.js'

const TAB_ICONS = {
  code: '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M5.5 3.5 2 8l3.5 4.5M10.5 12.5 14 8l-3.5-4.5"/></svg>',
  commits: '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="2"/><path d="M8 2v2M8 12v2M2 8h2M12 8h2" stroke="currentColor" stroke-width="1.2"/></svg>',
  changes: '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M3 8h10M8 3v10" stroke="currentColor" stroke-width="1.5"/></svg>',
}

export default {
  name: 'RepoLocalApp',
  components: { DiffViewer, FileTree },
  setup() {
    const api = inject('repoLocalApi')
    const route = useRoute()
    const router = useRouter()
    const fileTreeRef = ref(null)

    const bootLoading = ref(true)
    const bootError = ref('')
    const workspace = ref(null)
    const projects = ref([])
    const projectFilter = ref('all')
    const changedCount = computed(() => projects.value.filter((p) => p.has_changes === true).length)
    const filteredProjects = computed(() => {
      if (projectFilter.value === 'changed') {
        return projects.value.filter((p) => p.has_changes === true)
      }
      if (projectFilter.value === 'clean') {
        return projects.value.filter((p) => p.is_git && p.has_changes === false)
      }
      return projects.value
    })

    const activeProjectId = ref('')
    const activeProject = ref(null)
    const tab = ref('code')

    const selectedFile = ref('')
    const fileContent = ref('')
    const fileMeta = ref({})
    const fileLoading = ref(false)

    const commits = ref([])
    const commitsLoading = ref(false)
    const selectedCommit = ref(null)
    const commitFiles = ref([])
    const commitPatch = ref('')
    const commitError = ref('')
    const activeCommitFile = ref('')

    const changeFiles = ref([])
    const changeCount = ref(0)
    const changesLoading = ref(false)
    const selectedChangePath = ref('')
    const changePatch = ref('')
    const diffMode = ref('unstaged')

    const tabs = [
      { id: 'code', label: 'Code', icon: TAB_ICONS.code },
      { id: 'commits', label: 'History', icon: TAB_ICONS.commits },
      { id: 'changes', label: 'Changes', icon: TAB_ICONS.changes },
    ]

    async function loadWorkspace() {
      bootLoading.value = true
      bootError.value = ''
      try {
        const data = await api.getWorkspace()
        workspace.value = data
        projects.value = data.projects || []
      } catch (e) {
        bootError.value = e.message || 'Failed to load workspace'
      } finally {
        bootLoading.value = false
      }
    }

    function resetGitState() {
      commits.value = []
      selectedCommit.value = null
      commitFiles.value = []
      commitPatch.value = ''
      commitError.value = ''
      activeCommitFile.value = ''
      changeFiles.value = []
      selectedChangePath.value = ''
      changePatch.value = ''
      changeCount.value = 0
    }

    async function selectProject(p) {
      activeProjectId.value = p.id
      activeProject.value = p
      resetGitState()
      selectedFile.value = ''
      router.replace({ path: `/p/${encodeURIComponent(p.id)}/${tab.value}` })
      try {
        activeProject.value = await api.getProject(p.id)
      } catch {
        activeProject.value = p
      }
      if (tab.value === 'commits') loadCommits()
      if (tab.value === 'changes') loadChanges()
    }

    function setTab(id) {
      tab.value = id
      if (activeProjectId.value) {
        router.replace({ path: `/p/${encodeURIComponent(activeProjectId.value)}/${id}` })
      }
      if (id === 'commits') loadCommits()
      if (id === 'changes') loadChanges()
    }

    async function openFile(path) {
      selectedFile.value = path
      fileLoading.value = true
      fileContent.value = ''
      try {
        const data = await api.getFile(activeProjectId.value, path)
        fileMeta.value = data
        fileContent.value = data.content || ''
      } catch (e) {
        fileMeta.value = { binary: true, message: e.message }
      } finally {
        fileLoading.value = false
      }
    }

    async function loadCommits() {
      if (!activeProjectId.value || !activeProject.value?.is_git) return
      const projectId = activeProjectId.value
      commitsLoading.value = true
      selectedCommit.value = null
      commitFiles.value = []
      commitPatch.value = ''
      commitError.value = ''
      activeCommitFile.value = ''
      try {
        const data = await api.getCommits(projectId)
        if (projectId !== activeProjectId.value) return
        commits.value = data.commits || []
      } catch (e) {
        if (projectId === activeProjectId.value) {
          commits.value = []
          commitError.value = e.message || 'Failed to load commits'
        }
      } finally {
        if (projectId === activeProjectId.value) commitsLoading.value = false
      }
    }

    async function selectCommit(c) {
      selectedCommit.value = c
      commitPatch.value = ''
      commitError.value = ''
      activeCommitFile.value = ''
      try {
        const detail = await api.getCommit(activeProjectId.value, c.sha)
        commitFiles.value = detail.files || []
        const diff = await api.getDiff(activeProjectId.value, { mode: 'commit', sha: c.sha })
        commitPatch.value = diff.patch || ''
      } catch (e) {
        commitFiles.value = []
        commitPatch.value = ''
        commitError.value = e.message || 'Failed to load commit'
      }
    }

    async function viewCommitFile(f) {
      activeCommitFile.value = f.path
      try {
        const diff = await api.getDiff(activeProjectId.value, {
          mode: 'commit',
          sha: selectedCommit.value.sha,
          path: f.path,
        })
        commitPatch.value = diff.patch || ''
        commitError.value = ''
      } catch (e) {
        commitPatch.value = ''
        commitError.value = e.message
      }
    }

    async function loadChanges() {
      if (!activeProjectId.value || !activeProject.value?.is_git) return
      changesLoading.value = true
      try {
        const data = await api.getChanges(activeProjectId.value)
        changeFiles.value = data.files || []
        changeCount.value = data.counts?.total || 0
      } finally {
        changesLoading.value = false
      }
    }

    const activeChangeMode = ref('')

    function resolveChangeMode(f) {
      const hasUnstaged = !!(f?.unstaged || f?.untracked)
      const hasStaged = !!f?.staged
      if (diffMode.value === 'unstaged') {
        if (hasUnstaged) return 'unstaged'
        if (hasStaged) return 'staged'
        return 'unstaged'
      }
      if (hasStaged) return 'staged'
      if (hasUnstaged) return 'unstaged'
      return 'staged'
    }

    async function viewChangeFile(f) {
      selectedChangePath.value = f.path
      const mode = resolveChangeMode(f)
      activeChangeMode.value = mode
      try {
        const diff = await api.getDiff(activeProjectId.value, {
          mode,
          path: f.path,
        })
        changePatch.value = diff.patch || ''
      } catch (e) {
        changePatch.value = e.message
      }
    }

    watch(diffMode, () => {
      if (selectedChangePath.value) {
        const f = changeFiles.value.find((x) => x.path === selectedChangePath.value)
        if (f) viewChangeFile(f)
      }
    })

    async function syncFromRoute() {
      const params = route.params
      if (params.tab && ['code', 'commits', 'changes'].includes(params.tab)) {
        tab.value = params.tab
      }
      const pid = params.projectId
      if (!pid) return
      if (pid === activeProjectId.value && activeProject.value) {
        if (tab.value === 'commits') loadCommits()
        if (tab.value === 'changes') loadChanges()
        return
      }
      const p = projects.value.find((x) => x.id === pid)
      if (p) await selectProject(p)
    }

    watch(() => route.params, syncFromRoute)

    onMounted(async () => {
      await loadWorkspace()
      await syncFromRoute()
    })

    return {
      bootLoading,
      bootError,
      workspace,
      projects,
      projectFilter,
      filteredProjects,
      changedCount,
      activeProjectId,
      activeProject,
      tab,
      tabs,
      fileTreeRef,
      selectedFile,
      fileContent,
      fileMeta,
      fileLoading,
      commits,
      commitsLoading,
      selectedCommit,
      commitFiles,
      commitPatch,
      commitError,
      activeCommitFile,
      changeFiles,
      changeCount,
      changesLoading,
      selectedChangePath,
      changePatch,
      diffMode,
      activeChangeMode,
      selectProject,
      setTab,
      openFile,
      selectCommit,
      viewCommitFile,
      viewChangeFile,
      formatBytes,
      formatDate,
    }
  },
}
</script>

<style scoped>
.repo-local {
  --rl-bg: #070b10;
  --rl-bg-elevated: #0d1219;
  --rl-surface: #121a24;
  --rl-surface-2: #182230;
  --rl-border: rgba(148, 163, 184, 0.12);
  --rl-border-strong: rgba(148, 163, 184, 0.22);
  --rl-text: #e8eef6;
  --rl-muted: #7d8da6;
  --rl-accent: #38bdf8;
  --rl-accent-2: #818cf8;
  --rl-accent-dim: rgba(56, 189, 248, 0.12);
  --rl-hover: rgba(148, 163, 184, 0.08);
  --rl-green: #34d399;
  --rl-red: #f87171;
  --rl-font-mono: 'JetBrains Mono', 'Cascadia Code', Consolas, monospace;
  --rl-header-h: 52px;
  --rl-tabs-h: 44px;

  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: var(--rl-bg);
  background-image:
    radial-gradient(ellipse 80% 50% at 50% -20%, rgba(56, 189, 248, 0.08), transparent),
    radial-gradient(ellipse 60% 40% at 100% 100%, rgba(129, 140, 248, 0.06), transparent);
  color: var(--rl-text);
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  font-size: 13px;
}

.rl-header {
  flex-shrink: 0;
  height: var(--rl-header-h);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 0 16px;
  border-bottom: 1px solid var(--rl-border);
  background: rgba(13, 18, 25, 0.85);
  backdrop-filter: blur(12px);
}
.rl-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.rl-logo {
  color: var(--rl-accent);
  display: flex;
}
.rl-brand-text {
  display: flex;
  flex-direction: column;
  gap: 1px;
  line-height: 1.2;
}
.rl-brand-text strong {
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -0.02em;
}
.rl-root {
  font-size: 11px;
  color: var(--rl-muted);
}
.rl-project-head {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.rl-repo-name {
  font-weight: 600;
  font-size: 13px;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rl-branch {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-family: var(--rl-font-mono);
  padding: 3px 10px;
  border-radius: 999px;
  border: 1px solid var(--rl-border-strong);
  color: var(--rl-accent);
  background: var(--rl-accent-dim);
}
.rl-branch .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--rl-green);
  box-shadow: 0 0 8px var(--rl-green);
}
.rl-badge {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--rl-muted);
  padding: 3px 8px;
  border: 1px solid var(--rl-border);
  border-radius: 4px;
}

.rl-body {
  flex: 1;
  min-height: 0;
  display: flex;
}

.rl-sidebar {
  width: 248px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-right: 1px solid var(--rl-border);
  background: var(--rl-bg-elevated);
}

.rl-workspace {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.rl-tabs {
  flex-shrink: 0;
  height: var(--rl-tabs-h);
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 0 12px;
  border-bottom: 1px solid var(--rl-border);
  background: var(--rl-surface);
}
.rl-tabs button {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: var(--rl-muted);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: color 0.15s, background 0.15s, border-color 0.15s;
}
.rl-tabs button:hover {
  color: var(--rl-text);
  background: var(--rl-hover);
}
.rl-tabs button.active {
  color: var(--rl-text);
  border-color: var(--rl-border-strong);
  background: var(--rl-surface-2);
  box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.15);
}
.tab-icon {
  display: flex;
  opacity: 0.85;
}
.tab-count {
  font-size: 10px;
  min-width: 18px;
  text-align: center;
  padding: 1px 5px;
  border-radius: 999px;
  background: var(--rl-accent);
  color: #041018;
  font-weight: 700;
}

.rl-workspace-body {
  flex: 1;
  min-height: 0;
  display: flex;
}

.rl-panel-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--rl-muted);
  border-bottom: 1px solid var(--rl-border);
}
.rl-panel-head.compact {
  padding: 8px 12px;
}
.rl-panel-head .count {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--rl-surface-2);
  color: var(--rl-muted);
}

.rl-scroll {
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.rl-scroll::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
.rl-scroll::-webkit-scrollbar-thumb {
  background: rgba(148, 163, 184, 0.25);
  border-radius: 4px;
}
.rl-scroll::-webkit-scrollbar-track {
  background: transparent;
}

.rl-project-list {
  list-style: none;
  margin: 0;
  padding: 6px 8px 12px;
}
.rl-project-list li {
  margin-bottom: 2px;
}
.rl-project-list button {
  width: 100%;
  text-align: left;
  padding: 8px 10px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: var(--rl-text);
  cursor: pointer;
  transition: background 0.12s, border-color 0.12s;
}
.rl-project-list li.active button,
.rl-project-list button:hover {
  background: var(--rl-accent-dim);
  border-color: rgba(56, 189, 248, 0.25);
}
.rl-project-list .name {
  display: block;
  font-weight: 500;
  font-size: 13px;
}
.rl-project-list .path {
  display: block;
  font-size: 10px;
  font-family: var(--rl-font-mono);
  color: var(--rl-muted);
  margin-top: 2px;
}
.rl-project-list .branch {
  display: inline-block;
  margin-top: 4px;
  font-size: 10px;
  color: var(--rl-green);
}

.rl-center-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 0;
  padding: 24px;
}
.rl-center-empty.inner {
  height: 100%;
}
.empty-card {
  padding: 24px 32px;
  border-radius: 12px;
  border: 1px dashed var(--rl-border-strong);
  background: var(--rl-surface);
  color: var(--rl-muted);
  text-align: center;
  max-width: 320px;
}
.empty-card.small {
  padding: 16px 24px;
  font-size: 12px;
}

.rl-code-layout {
  flex: 1;
  min-height: 0;
  display: flex;
}
.rl-tree-panel {
  width: 280px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-right: 1px solid var(--rl-border);
  background: var(--rl-bg-elevated);
}
.rl-preview-panel {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--rl-surface);
}
.rl-preview-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--rl-border);
  background: var(--rl-surface-2);
}
.rl-preview-head .path {
  font-family: var(--rl-font-mono);
  font-size: 12px;
  color: var(--rl-accent);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rl-preview-head .meta {
  flex-shrink: 0;
  font-size: 11px;
  color: var(--rl-muted);
}
.rl-mode-badge {
  flex-shrink: 0;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  background: rgba(56, 139, 253, 0.15);
  color: var(--rl-accent);
  border: 1px solid rgba(56, 139, 253, 0.35);
}
.rl-mode-badge.alt {
  background: rgba(210, 153, 34, 0.15);
  color: #e0b341;
  border-color: rgba(210, 153, 34, 0.35);
}
.preview-scroll {
  background: #0a0f14;
}
.rl-code {
  margin: 0;
  padding: 16px;
  font-family: var(--rl-font-mono);
  font-size: 12px;
  line-height: 1.55;
  color: #c9d7e8;
  tab-size: 2;
}

.rl-split-layout {
  flex: 1;
  min-height: 0;
  display: flex;
}
.rl-list-panel {
  width: 300px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-right: 1px solid var(--rl-border);
  background: var(--rl-bg-elevated);
}
.rl-detail-panel {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--rl-surface);
}
.rl-detail-head {
  flex-shrink: 0;
  padding: 12px 16px;
  border-bottom: 1px solid var(--rl-border);
}
.rl-detail-head h3 {
  margin: 0 0 4px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.35;
}
.rl-detail-head .sub {
  margin: 0;
  font-size: 12px;
  color: var(--rl-muted);
}
.rl-detail-split {
  flex: 1;
  min-height: 0;
  display: flex;
}
.rl-sublist {
  width: 260px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-right: 1px solid var(--rl-border);
}
.rl-diff-wrap {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.rl-diff-wrap .rl-scroll {
  padding: 12px;
}

.rl-commit-list {
  list-style: none;
  margin: 0;
  padding: 4px 0;
}
.rl-commit-list button {
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  margin: 0 6px;
  width: calc(100% - 12px);
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: var(--rl-text);
  cursor: pointer;
}
.rl-commit-list li.active button,
.rl-commit-list button:hover {
  background: var(--rl-accent-dim);
  border-color: rgba(56, 189, 248, 0.2);
}
.rl-commit-list .sha {
  font-family: var(--rl-font-mono);
  font-size: 11px;
  color: var(--rl-accent);
  font-weight: 600;
}
.rl-commit-list .msg {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  line-height: 1.35;
}
.rl-commit-list .meta {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: var(--rl-muted);
}

.rl-mini-list {
  list-style: none;
  margin: 0;
  padding: 4px 0;
}
.rl-mini-list button {
  width: calc(100% - 12px);
  margin: 0 6px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  text-align: left;
  padding: 8px 10px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  color: var(--rl-text);
  cursor: pointer;
  font-size: 11px;
}
.rl-mini-list li.active button,
.rl-mini-list button:hover {
  background: var(--rl-hover);
  border-color: var(--rl-border);
}
.rl-mini-list .st {
  flex-shrink: 0;
  font-weight: 700;
  color: var(--rl-green);
  font-family: var(--rl-font-mono);
}
.rl-mini-list .path {
  font-family: var(--rl-font-mono);
  word-break: break-all;
  line-height: 1.35;
  color: var(--rl-muted);
}
.rl-mini-list li.active .path {
  color: var(--rl-text);
}

.seg {
  display: flex;
  gap: 2px;
  padding: 2px;
  border-radius: 6px;
  background: var(--rl-surface-2);
}
.seg button {
  padding: 3px 8px;
  font-size: 10px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: var(--rl-muted);
  cursor: pointer;
}
.seg button.active {
  background: var(--rl-accent-dim);
  color: var(--rl-accent);
}
.seg-count {
  margin-left: 5px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 14px;
  height: 14px;
  padding: 0 4px;
  border-radius: 7px;
  background: rgba(210, 153, 34, 0.2);
  color: #e0b341;
  font-size: 9px;
  font-weight: 600;
}

.rl-filter-bar {
  margin: 6px 10px 8px;
}

.rl-panel-head .count .of {
  color: var(--rl-muted);
  font-weight: 400;
}

.rl-change-dot {
  display: inline-block;
  width: 7px;
  height: 7px;
  margin-right: 6px;
  border-radius: 50%;
  background: #e0b341;
  vertical-align: middle;
  flex-shrink: 0;
}

.rl-placeholder.small {
  padding: 10px 14px;
  font-size: 11px;
}

.rl-placeholder,
.rl-muted {
  padding: 16px;
  color: var(--rl-muted);
  font-size: 12px;
}
.rl-error {
  padding: 12px;
  margin: 8px;
  font-size: 12px;
  color: var(--rl-red);
  background: rgba(248, 113, 113, 0.1);
  border-radius: 6px;
}
</style>
