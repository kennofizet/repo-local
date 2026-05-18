<template>
  <div class="repo-local" :class="{ loading: bootLoading }">
    <header class="rl-header">
      <div class="rl-brand">
        <span class="rl-logo">◆</span>
        <strong>Repo Local</strong>
        <span v-if="workspace" class="rl-root">{{ workspace.root_name }}</span>
      </div>
      <div v-if="activeProject" class="rl-project-head">
        <span class="rl-repo-name">{{ activeProject.name }}</span>
        <span v-if="activeProject.branch" class="rl-branch">⎇ {{ activeProject.branch }}</span>
        <span v-if="!activeProject?.is_git" class="rl-badge">no git</span>
      </div>
    </header>

    <div class="rl-body">
      <aside class="rl-sidebar">
        <div class="rl-sidebar-title">Projects</div>
        <div v-if="bootError" class="rl-error">{{ bootError }}</div>
        <ul class="rl-project-list">
          <li
            v-for="p in projects"
            :key="p.id"
            :class="{ active: p.id === activeProjectId }"
          >
            <button type="button" @click="selectProject(p)">
              <span class="name">{{ p.name }}</span>
              <span class="path">{{ p.path || '.' }}</span>
              <span v-if="p.branch" class="branch">{{ p.branch }}</span>
            </button>
          </li>
        </ul>
      </aside>

      <section v-if="!activeProjectId" class="rl-empty">
        <p>Select a project from the workspace root to browse files and git history.</p>
      </section>

      <div v-else-if="activeProjectId && !activeProject" class="rl-empty">
        <p>Loading project…</p>
      </div>

      <div v-else-if="activeProject" class="rl-project-pane">
        <nav class="rl-tabs">
          <button
            v-for="t in tabs"
            :key="t.id"
            type="button"
            :class="{ active: tab === t.id }"
            @click="setTab(t.id)"
          >
            {{ t.label }}
            <span v-if="t.id === 'changes' && changeCount" class="count">{{ changeCount }}</span>
          </button>
        </nav>

        <div class="rl-main">
          <!-- CODE -->
          <div v-if="tab === 'code'" class="rl-code-layout">
            <aside class="rl-tree">
              <div class="rl-breadcrumb">
                <button type="button" @click="loadTree('')">/</button>
                <template v-for="(seg, i) in pathSegments" :key="i">
                  <span>/</span>
                  <button type="button" @click="loadTree(pathSegments.slice(0, i + 1).join('/'))">{{ seg }}</button>
                </template>
              </div>
              <div v-if="treeLoading" class="rl-muted">Loading tree…</div>
              <ul v-else class="rl-tree-list">
                <li v-for="entry in treeEntries" :key="entry.path">
                  <button
                    type="button"
                    :class="{ dir: entry.type === 'dir', file: entry.type === 'file' }"
                    @click="onTreeClick(entry)"
                  >
                    <span class="icon">{{ entry.type === 'dir' ? '📁' : '📄' }}</span>
                    <span class="label">{{ entry.name }}</span>
                    <span v-if="entry.git_status" class="status" :data-s="entry.git_status">{{ entry.git_status }}</span>
                  </button>
                </li>
              </ul>
            </aside>
            <article class="rl-file-pane">
              <div v-if="!selectedFile" class="rl-placeholder">
                Choose a file to view its contents.
              </div>
              <template v-else>
                <div class="rl-file-meta">
                  <code>{{ selectedFile }}</code>
                  <span v-if="fileMeta.size != null">{{ formatBytes(fileMeta.size) }}</span>
                </div>
                <div v-if="fileLoading" class="rl-muted">Loading file…</div>
                <div v-else-if="fileMeta.binary" class="rl-placeholder">{{ fileMeta.message }}</div>
                <pre v-else class="rl-code"><code>{{ fileContent }}</code></pre>
              </template>
            </article>
          </div>

          <!-- COMMITS -->
          <div v-else-if="tab === 'commits'" class="rl-tab-panel">
            <div class="rl-split">
              <div class="rl-list-pane">
                <div v-if="!activeProject?.is_git" class="rl-placeholder">Not a git repository.</div>
                <div v-else-if="commitsLoading" class="rl-muted">Loading commits…</div>
                <div v-else-if="commitError" class="rl-error">{{ commitError }}</div>
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
              <div class="rl-detail-pane">
                <div v-if="!selectedCommit" class="rl-placeholder">Select a commit to see changed files and diff.</div>
                <template v-else>
                  <h3>{{ selectedCommit.message }}</h3>
                  <p class="rl-muted">{{ selectedCommit.author }} · {{ formatDate(selectedCommit.date) }}</p>
                  <ul class="rl-changed-files">
                    <li v-for="f in commitFiles" :key="f.path">
                      <button type="button" @click="viewCommitFile(f)">
                        <span class="st">{{ f.status }}</span> {{ f.path }}
                      </button>
                    </li>
                  </ul>
                  <div v-if="commitError" class="rl-error">{{ commitError }}</div>
                  <DiffViewer v-else-if="commitPatch" :patch="commitPatch" />
                </template>
              </div>
            </div>
          </div>

          <!-- CHANGES -->
          <div v-else-if="tab === 'changes'" class="rl-tab-panel">
            <div class="rl-split">
              <div class="rl-list-pane">
                <div v-if="!activeProject?.is_git" class="rl-placeholder">Not a git repository.</div>
                <div v-else-if="changesLoading" class="rl-muted">Loading changes…</div>
                <div v-else class="rl-changes-toolbar">
                  <button type="button" :class="{ active: diffMode === 'unstaged' }" @click="diffMode = 'unstaged'">Unstaged</button>
                  <button type="button" :class="{ active: diffMode === 'staged' }" @click="diffMode = 'staged'">Staged</button>
                </div>
                <ul v-if="activeProject?.is_git" class="rl-changed-files">
                  <li
                    v-for="f in changeFiles"
                    :key="f.path"
                    :class="{ active: selectedChangePath === f.path }"
                  >
                    <button type="button" @click="viewChangeFile(f)">
                      <span class="st">{{ f.worktree_status || f.index_status }}</span>
                      {{ f.path }}
                    </button>
                  </li>
                </ul>
              </div>
              <div class="rl-detail-pane">
                <div v-if="!selectedChangePath" class="rl-placeholder">Select a changed file to view diff.</div>
                <template v-else>
                  <h3><code>{{ selectedChangePath }}</code></h3>
                  <DiffViewer v-if="changePatch" :patch="changePatch" />
                </template>
              </div>
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
import { formatBytes, formatDate } from '../utils/format.js'

export default {
  name: 'RepoLocalApp',
  components: { DiffViewer },
  setup() {
    const api = inject('repoLocalApi')
    const route = useRoute()
    const router = useRouter()

    const bootLoading = ref(true)
    const bootError = ref('')
    const workspace = ref(null)
    const projects = ref([])

    const activeProjectId = ref('')
    const activeProject = ref(null)
    const tab = ref('code')

    const treePath = ref('')
    const treeEntries = ref([])
    const treeLoading = ref(false)

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

    const changeFiles = ref([])
    const changeCount = ref(0)
    const changesLoading = ref(false)
    const selectedChangePath = ref('')
    const changePatch = ref('')
    const diffMode = ref('unstaged')

    const tabs = [
      { id: 'code', label: 'Code' },
      { id: 'commits', label: 'History' },
      { id: 'changes', label: 'Changes' },
    ]

    const pathSegments = computed(() => (treePath.value ? treePath.value.split('/') : []))

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
      changeFiles.value = []
      selectedChangePath.value = ''
      changePatch.value = ''
      changeCount.value = 0
    }

    async function selectProject(p) {
      activeProjectId.value = p.id
      activeProject.value = p
      resetGitState()
      router.replace({ path: `/p/${encodeURIComponent(p.id)}/${tab.value}` })
      try {
        activeProject.value = await api.getProject(p.id)
      } catch {
        activeProject.value = p
      }
      selectedFile.value = ''
      treePath.value = ''
      await loadTree('')
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

    async function loadTree(path) {
      if (!activeProjectId.value) return
      treeLoading.value = true
      treePath.value = path
      try {
        const data = await api.getTree(activeProjectId.value, path)
        treeEntries.value = data.entries || []
      } catch (e) {
        treeEntries.value = []
      } finally {
        treeLoading.value = false
      }
    }

    function onTreeClick(entry) {
      if (entry.type === 'dir') {
        loadTree(entry.path)
        selectedFile.value = ''
        return
      }
      openFile(entry.path)
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
        if (projectId === activeProjectId.value) {
          commitsLoading.value = false
        }
      }
    }

    async function selectCommit(c) {
      selectedCommit.value = c
      commitPatch.value = ''
      commitError.value = ''
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
      try {
        const diff = await api.getDiff(activeProjectId.value, {
          mode: 'commit',
          sha: selectedCommit.value.sha,
          path: f.path,
        })
        commitPatch.value = diff.patch || ''
      } catch (e) {
        commitPatch.value = e.message
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

    async function viewChangeFile(f) {
      selectedChangePath.value = f.path
      try {
        const diff = await api.getDiff(activeProjectId.value, {
          mode: diffMode.value,
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
      activeProjectId,
      activeProject,
      tab,
      tabs,
      treePath,
      treeEntries,
      treeLoading,
      pathSegments,
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
      changeFiles,
      changeCount,
      changesLoading,
      selectedChangePath,
      changePatch,
      diffMode,
      selectProject,
      setTab,
      loadTree,
      onTreeClick,
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
  --rl-bg: #0d1117;
  --rl-bg-subtle: #161b22;
  --rl-border: #30363d;
  --rl-text: #e6edf3;
  --rl-muted: #8b949e;
  --rl-accent: #4493f8;
  --rl-green: #3fb950;
  --rl-red: #f85149;
  min-height: 100vh;
  background: var(--rl-bg);
  color: var(--rl-text);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
  font-size: 14px;
}
.rl-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--rl-border);
  background: var(--rl-bg-subtle);
}
.rl-brand { display: flex; align-items: center; gap: 8px; }
.rl-logo { color: var(--rl-accent); }
.rl-root { color: var(--rl-muted); font-size: 12px; }
.rl-project-head { display: flex; align-items: center; gap: 8px; }
.rl-repo-name { font-weight: 600; }
.rl-branch, .rl-badge {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid var(--rl-border);
  color: var(--rl-muted);
}
.rl-body { display: flex; flex: 1; min-height: calc(100vh - 49px); }
.rl-project-pane { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.rl-sidebar {
  width: 260px;
  border-right: 1px solid var(--rl-border);
  background: var(--rl-bg-subtle);
  flex-shrink: 0;
}
.rl-sidebar-title {
  padding: 12px 16px;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--rl-muted);
}
.rl-project-list { list-style: none; margin: 0; padding: 0 8px 16px; }
.rl-project-list li { margin-bottom: 4px; }
.rl-project-list button {
  width: 100%;
  text-align: left;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  color: var(--rl-text);
  padding: 8px 10px;
  cursor: pointer;
}
.rl-project-list li.active button,
.rl-project-list button:hover {
  background: rgba(68, 147, 248, 0.12);
  border-color: var(--rl-border);
}
.rl-project-list .path { display: block; font-size: 11px; color: var(--rl-muted); }
.rl-project-list .branch { font-size: 11px; color: var(--rl-green); }
.rl-tabs {
  display: flex;
  gap: 4px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--rl-border);
  background: var(--rl-bg-subtle);
}
.rl-tabs button {
  background: transparent;
  border: none;
  color: var(--rl-muted);
  padding: 8px 12px;
  border-radius: 6px;
  cursor: pointer;
}
.rl-tabs button.active { color: var(--rl-text); background: rgba(255,255,255,0.06); }
.rl-tabs .count {
  margin-left: 6px;
  background: var(--rl-accent);
  color: #fff;
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 999px;
}
.rl-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.rl-code-layout { display: flex; flex: 1; min-height: 0; }
.rl-tab-panel { display: flex; flex: 1; min-height: 0; flex-direction: column; }
.rl-tab-panel .rl-split { flex: 1; }
.rl-tree {
  width: 280px;
  border-right: 1px solid var(--rl-border);
  overflow: auto;
  flex-shrink: 0;
}
.rl-breadcrumb {
  padding: 8px 12px;
  border-bottom: 1px solid var(--rl-border);
  font-size: 12px;
  word-break: break-all;
}
.rl-breadcrumb button {
  background: none;
  border: none;
  color: var(--rl-accent);
  cursor: pointer;
  padding: 0;
}
.rl-tree-list { list-style: none; margin: 0; padding: 4px 0; }
.rl-tree-list button {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  background: none;
  border: none;
  color: var(--rl-text);
  text-align: left;
  cursor: pointer;
  font-size: 13px;
}
.rl-tree-list button:hover { background: rgba(255,255,255,0.04); }
.rl-tree-list .status { margin-left: auto; font-size: 11px; color: var(--rl-green); }
.rl-file-pane { flex: 1; overflow: auto; padding: 0; }
.rl-file-meta {
  display: flex;
  justify-content: space-between;
  padding: 10px 16px;
  border-bottom: 1px solid var(--rl-border);
  font-size: 12px;
  color: var(--rl-muted);
}
.rl-code {
  margin: 0;
  padding: 16px;
  font-size: 12px;
  line-height: 1.5;
  overflow: auto;
}
.rl-split { display: flex; flex: 1; min-height: 0; }
.rl-list-pane {
  width: 340px;
  border-right: 1px solid var(--rl-border);
  overflow: auto;
}
.rl-detail-pane { flex: 1; overflow: auto; padding: 16px; }
.rl-commit-list { list-style: none; margin: 0; padding: 0; }
.rl-commit-list button {
  width: 100%;
  text-align: left;
  padding: 12px 16px;
  background: none;
  border: none;
  border-bottom: 1px solid var(--rl-border);
  color: var(--rl-text);
  cursor: pointer;
}
.rl-commit-list li.active button { background: rgba(68, 147, 248, 0.1); }
.rl-commit-list .sha { font-family: monospace; color: var(--rl-accent); margin-right: 8px; }
.rl-commit-list .msg { display: block; margin-top: 4px; }
.rl-commit-list .meta { display: block; font-size: 12px; color: var(--rl-muted); margin-top: 4px; }
.rl-changed-files { list-style: none; margin: 0; padding: 0; }
.rl-changed-files button {
  width: 100%;
  text-align: left;
  padding: 8px 16px;
  background: none;
  border: none;
  border-bottom: 1px solid var(--rl-border);
  color: var(--rl-text);
  cursor: pointer;
  font-family: monospace;
  font-size: 12px;
}
.rl-changed-files li.active button { background: rgba(68, 147, 248, 0.1); }
.rl-changed-files .st { color: var(--rl-green); margin-right: 8px; }
.rl-changes-toolbar { padding: 8px; display: flex; gap: 8px; }
.rl-changes-toolbar button {
  flex: 1;
  padding: 6px;
  border-radius: 6px;
  border: 1px solid var(--rl-border);
  background: transparent;
  color: var(--rl-muted);
  cursor: pointer;
}
.rl-changes-toolbar button.active { color: var(--rl-text); border-color: var(--rl-accent); }
.rl-empty, .rl-placeholder { padding: 48px 24px; color: var(--rl-muted); text-align: center; }
.rl-error { padding: 12px; color: var(--rl-red); font-size: 12px; }
.rl-muted { padding: 12px; color: var(--rl-muted); }
section.rl-empty { flex: 1; display: flex; align-items: center; justify-content: center; }
</style>
