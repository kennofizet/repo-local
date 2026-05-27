<template>
  <aside class="rl-tinker-panel">
    <div class="rl-tinker-head">
      <span class="rl-tinker-title">Tinker zone</span>
      <div class="rl-tinker-head-right">
        <nav class="rl-tinker-tab-nav" aria-label="Tinker panel tabs">
          <button
            type="button"
            class="rl-tinker-tab"
            :class="{ active: innerTab === 'query' }"
            @click="setTab('query')"
          >
            Query
          </button>
          <span class="rl-tinker-tab-sep" aria-hidden="true">|</span>
          <button
            type="button"
            class="rl-tinker-tab"
            :class="{ active: innerTab === 'usercase' }"
            @click="setTab('usercase')"
          >
            User case
          </button>
        </nav>
        <button type="button" class="rl-icon-btn" title="Close panel" @click="$emit('close')">×</button>
      </div>
    </div>

    <div v-if="selectionText" class="rl-selection-bar">
      <code>{{ selectionPreview }}</code>
      <div class="rl-selection-actions">
        <button type="button" @click="copySelection">Copy</button>
        <button type="button" @click="insertSelection">To query</button>
      </div>
    </div>

    <div class="rl-tinker-body rl-scrollbar">
      <!-- Query -->
      <div v-show="innerTab === 'query'" class="rl-tinker-section">
        <p class="rl-hint">
          Runs like Tinker in the selected Laravel project.
          <template v-if="defaultUser"> Starts logged in as <strong>{{ defaultLabel }}</strong>, then logs out.</template>
          <template v-else> No default user — runs without login.</template>
        </p>
        <label class="rl-field-label">PHP query</label>
        <textarea
          :value="queryDraft"
          class="rl-code-input rl-scrollbar"
          rows="12"
          spellcheck="false"
          placeholder="return app(SomeService::class)->method();"
          @input="onQueryInput"
          @paste="onQueryPaste"
        />
        <div class="rl-tinker-actions">
          <button type="button" class="rl-run-btn" :disabled="running || !queryDraft.trim()" @click="runQuery">
            {{ running ? 'Running…' : 'Run query' }}
          </button>
        </div>
        <div v-if="lastRun" class="rl-run-result">
          <div class="rl-run-meta">
            {{ lastRun.duration_ms }} ms
            <span v-if="lastRun.auth_user_id != null" class="auth-hint"> · auth #{{ lastRun.auth_user_id }}</span>
            <span v-else-if="!lastRun.error" class="auth-hint warn"> · not logged in</span>
          </div>
          <div v-if="lastRun.error" class="rl-error compact">
            {{ lastRun.error.message }}
            <span v-if="lastRun.error.line" class="loc"> ({{ lastRun.error.file }}:{{ lastRun.error.line }})</span>
          </div>
          <p v-else-if="lastRun.result == null && lastRun.result_type === 'null'" class="rl-null-hint">
            Result is <code>null</code>. For <code>currentUser()</code>, set a <strong>default test user</strong> in the User case tab.
          </p>
          <pre v-if="lastRun.output" class="rl-out-block stdout"><code>{{ lastRun.output }}</code></pre>
          <template v-if="!lastRun.error">
            <div class="rl-result-head">Result <span class="type-tag">{{ lastRun.result_type || 'mixed' }}</span></div>
            <pre class="rl-out-block result"><code>{{ runResultDisplay(lastRun) }}</code></pre>
          </template>
        </div>
      </div>

      <!-- User case -->
      <div v-show="innerTab === 'usercase'" class="rl-tinker-section">
        <p class="rl-hint">
          Type PHP that returns a <code>User</code> or user id. Run it to add/update a test user (label uses name from result, otherwise <code>#id</code> only).
        </p>
        <label class="rl-field-label">Get user (PHP)</label>
        <textarea
          :value="resolveCode"
          class="rl-code-input rl-scrollbar"
          rows="8"
          spellcheck="false"
          placeholder="$id = 1;&#10;return \App\Models\User::queryForSystemJob()->find($id);"
          @input="onResolveInput"
          @paste="onResolvePaste"
        />
        <div class="rl-tinker-actions">
          <button type="button" class="rl-run-btn" :disabled="resolving || !resolveCode.trim()" @click="resolveAndAddUser">
            {{ resolving ? 'Running…' : 'Run & add test user' }}
          </button>
        </div>
        <div v-if="lastUserRun" class="rl-run-result">
          <div class="rl-run-meta">{{ lastUserRun.duration_ms }} ms</div>
          <div v-if="lastUserRun.error" class="rl-error compact">{{ lastUserRun.error.message }}</div>
          <pre v-if="lastUserRun.output" class="rl-out-block stdout"><code>{{ lastUserRun.output }}</code></pre>
          <template v-if="!lastUserRun.error">
            <div class="rl-result-head">Result <span class="type-tag">{{ lastUserRun.result_type || 'mixed' }}</span></div>
            <pre class="rl-out-block result"><code>{{ runResultDisplay(lastUserRun) }}</code></pre>
          </template>
        </div>

        <div class="rl-user-list-head">
          <span>Test users</span>
          <span class="count">{{ users.length }}</span>
        </div>
        <ul v-if="users.length" class="rl-user-list">
          <li v-for="u in users" :key="u.id" :class="{ default: u.id === defaultEntryId }">
            <div class="rl-user-main">
              <button type="button" class="rl-user-label" :title="u.resolveCode || ''" @click="loadUserResolveCode(u)">
                {{ displayLabel(u) }}
              </button>
              <span v-if="u.id === defaultEntryId" class="rl-default-tag">default</span>
            </div>
            <div class="rl-user-actions">
              <button
                v-if="u.id !== defaultEntryId"
                type="button"
                title="Set as default for query runs"
                @click="setDefault(u.id)"
              >
                ★
              </button>
              <button type="button" class="danger" title="Remove" @click="removeUser(u.id)">⌫</button>
            </div>
          </li>
        </ul>
        <p v-else class="rl-muted small">No test users yet. Run your get-user code above.</p>
      </div>
    </div>
  </aside>
</template>

<script>
import { computed, ref, watch } from 'vue'
import { sanitizeSelectionForCode } from '../utils/tinkerSelection.js'

export default {
  name: 'TinkerSupportPanel',
  props: {
    projectId: { type: String, required: true },
    api: { type: Object, required: true },
    selectionText: { type: String, default: '' },
    queryDirty: { type: Boolean, default: false },
    detailKey: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    defaultEntryId: { type: String, default: null },
    defaultUser: { type: Object, default: null },
    resolveCode: { type: String, required: true },
    queryDraft: { type: String, required: true },
    innerTab: { type: String, default: 'query' },
    displayLabel: { type: Function, required: true },
  },
  emits: [
    'close',
    'update:resolveCode',
    'update:queryDraft',
    'query-user-edit',
    'update:innerTab',
    'add-user',
    'remove-user',
    'set-default',
  ],
  setup(props, { emit }) {
    const running = ref(false)
    const resolving = ref(false)
    const lastRun = ref(null)
    const lastUserRun = ref(null)

    const selectionPreview = computed(() => {
      const t = props.selectionText || ''
      return t.length > 80 ? `${t.slice(0, 80)}…` : t
    })

    const defaultLabel = computed(() =>
      props.defaultUser ? props.displayLabel(props.defaultUser) : '',
    )

    function resetQueryTabUi() {
      lastRun.value = null
      lastUserRun.value = null
    }

    watch(() => props.projectId, resetQueryTabUi)

    watch(
      () => props.detailKey,
      () => {
        resetQueryTabUi()
        emit('update:innerTab', 'query')
      },
    )

    watch(
      () => props.selectionText,
      (text) => {
        const cleaned = sanitizeSelectionForCode(text)
        if (!cleaned || props.queryDirty || props.queryDraft.trim()) return
        emit('update:queryDraft', cleaned)
        emit('update:innerTab', 'query')
      },
    )

    function setTab(tab) {
      emit('update:innerTab', tab)
    }

    function onQueryInput(e) {
      emit('update:queryDraft', e.target.value)
      emit('query-user-edit')
    }

    function onResolveInput(e) {
      emit('update:resolveCode', e.target.value)
    }

    function applyPastedText(current, pasted) {
      const cleaned = sanitizeSelectionForCode(pasted)
      if (!cleaned) return current
      if (!current.trim()) return cleaned
      return `${current}\n${cleaned}`
    }

    function onQueryPaste(e) {
      const pasted = e.clipboardData?.getData('text') || ''
      if (!pasted) return
      e.preventDefault()
      const cleaned = sanitizeSelectionForCode(pasted)
      if (!cleaned) return

      if (props.queryDirty) {
        const next = props.queryDraft.trim() ? `${props.queryDraft}\n${cleaned}` : cleaned
        emit('update:queryDraft', next)
        emit('query-user-edit')
        return
      }
      if (!props.queryDraft.trim()) {
        emit('update:queryDraft', cleaned)
        return
      }
      emit('update:queryDraft', `${props.queryDraft}\n${cleaned}`)
    }

    function onResolvePaste(e) {
      const pasted = e.clipboardData?.getData('text') || ''
      if (!pasted) return
      e.preventDefault()
      emit('update:resolveCode', applyPastedText(props.resolveCode, pasted))
    }

    function formatResult(value) {
      if (value === null) return 'null'
      if (value === undefined) return 'undefined'
      if (typeof value === 'string') return value
      try {
        return JSON.stringify(value, null, 2)
      } catch {
        return String(value)
      }
    }

    function runResultDisplay(run) {
      if (run?.result_text) return run.result_text
      return formatResult(run?.result)
    }

    async function copySelection() {
      if (!props.selectionText) return
      try {
        await navigator.clipboard.writeText(props.selectionText)
      } catch {
        /* ignore */
      }
    }

    function insertSelection() {
      const cleaned = sanitizeSelectionForCode(props.selectionText)
      if (!cleaned) return

      let next
      if (props.queryDirty) {
        next = props.queryDraft.trim() ? `${props.queryDraft}\n${cleaned}` : cleaned
        emit('update:queryDraft', next)
        emit('query-user-edit')
      } else if (!props.queryDraft.trim()) {
        emit('update:queryDraft', cleaned)
      } else {
        next = `${props.queryDraft}\n${cleaned}`
        emit('update:queryDraft', next)
      }
      emit('update:innerTab', 'query')
    }

    async function runQuery() {
      const code = props.queryDraft.trim()
      if (!code) return
      running.value = true
      lastRun.value = null
      try {
        const userId = props.defaultUser?.laravelUserId ?? null
        const data = await props.api.runTinker(props.projectId, { code, userId })
        lastRun.value = data
      } catch (e) {
        lastRun.value = {
          duration_ms: 0,
          error: { message: e.message || 'Request failed' },
          output: '',
          result: null,
        }
      } finally {
        running.value = false
      }
    }

    async function resolveAndAddUser() {
      const code = props.resolveCode.trim()
      if (!code) return
      resolving.value = true
      lastUserRun.value = null
      try {
        const data = await props.api.runTinker(props.projectId, { code, userId: null })
        const id = extractUserId(data?.result)
        if (id == null) {
          lastUserRun.value = {
            duration_ms: data?.duration_ms ?? 0,
            error: { message: 'Code did not return a user id or User model.' },
            output: data?.output || '',
            result: data?.result,
          }
          return
        }
        const name = extractUserName(data?.result)
        emit('add-user', { laravelUserId: id, name, resolveCode: code })
        lastUserRun.value = {
          duration_ms: data?.duration_ms ?? 0,
          error: null,
          output: data?.output || '',
          result: data?.result,
        }
      } catch (e) {
        lastUserRun.value = {
          duration_ms: 0,
          error: { message: e.message || 'Run failed' },
        }
      } finally {
        resolving.value = false
      }
    }

    function loadUserResolveCode(u) {
      if (u?.resolveCode) {
        emit('update:resolveCode', u.resolveCode)
      }
    }

    function extractUserId(result) {
      if (result == null) return null
      if (typeof result === 'number') return result
      if (typeof result === 'string' && /^\d+$/.test(result)) return Number(result)
      if (typeof result === 'object') {
        if (result.__id != null) return Number(result.__id)
        if (result.id != null) return Number(result.id)
        if (result.user_id != null) return Number(result.user_id)
      }
      return null
    }

    function extractUserName(result) {
      if (result == null || typeof result !== 'object') return ''
      const parts = [result.name, result.full_name, result.email].filter(Boolean)
      return parts[0] ? String(parts[0]) : ''
    }

    function removeUser(id) {
      emit('remove-user', id)
    }

    function setDefault(id) {
      emit('set-default', id)
    }

    return {
      running,
      resolving,
      lastRun,
      lastUserRun,
      selectionPreview,
      defaultLabel,
      setTab,
      onQueryInput,
      onResolveInput,
      formatResult,
      runResultDisplay,
      copySelection,
      insertSelection,
      runQuery,
      resolveAndAddUser,
      loadUserResolveCode,
      removeUser,
      setDefault,
    }
  },
}
</script>

<style scoped>
.rl-tinker-panel {
  width: 100%;
  height: 100%;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  min-height: 0;
  border-left: 1px solid var(--rl-border);
  background: linear-gradient(180deg, #0e1520 0%, var(--rl-bg-elevated) 48px);
  box-shadow: -8px 0 24px rgba(0, 0, 0, 0.25);
}
.rl-tinker-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 14px;
  border-bottom: 1px solid var(--rl-border);
  background: rgba(56, 189, 248, 0.04);
}
.rl-tinker-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--rl-muted);
  flex-shrink: 0;
}
.rl-tinker-head-right {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.rl-tinker-tab-nav {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 11px;
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0;
}
.rl-tinker-tab {
  border: none;
  background: transparent;
  padding: 2px 0;
  margin: 0;
  color: var(--rl-muted);
  cursor: pointer;
  font: inherit;
  line-height: 1.3;
  transition: color 0.12s ease;
}
.rl-tinker-tab:hover {
  color: var(--rl-text);
}
.rl-tinker-tab.active {
  color: var(--rl-accent);
  font-weight: 600;
}
.rl-tinker-tab-sep {
  color: var(--rl-border-strong);
  user-select: none;
  font-weight: 400;
}
.rl-icon-btn {
  border: none;
  background: transparent;
  color: var(--rl-muted);
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  padding: 0 4px;
}
.rl-icon-btn:hover {
  color: var(--rl-text);
}
.rl-selection-bar {
  flex-shrink: 0;
  padding: 8px 10px;
  border-bottom: 1px solid var(--rl-border);
  background: var(--rl-surface);
}
.rl-selection-bar code {
  display: block;
  font-family: var(--rl-font-mono);
  font-size: 10px;
  color: var(--rl-accent);
  word-break: break-all;
  margin-bottom: 6px;
}
.rl-selection-actions {
  display: flex;
  gap: 6px;
}
.rl-selection-actions button {
  font-size: 10px;
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid var(--rl-border-strong);
  background: var(--rl-surface-2);
  color: var(--rl-text);
  cursor: pointer;
}
.rl-tinker-body {
  flex: 1;
  min-height: 0;
  padding: 10px 12px 12px;
  overflow: auto;
  overscroll-behavior: contain;
}
.rl-tinker-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rl-hint {
  margin: 0;
  font-size: 11px;
  color: var(--rl-muted);
  line-height: 1.4;
}
.rl-hint strong {
  color: var(--rl-accent);
  font-weight: 600;
}
.rl-field-label {
  display: block;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--rl-muted);
  margin-bottom: 4px;
}
.rl-code-input {
  width: 100%;
  box-sizing: border-box;
  font-family: var(--rl-font-mono);
  font-size: 11px;
  line-height: 1.5;
  padding: 10px 11px;
  border-radius: 8px;
  border: 1px solid rgba(56, 189, 248, 0.18);
  background: #060a0e;
  color: #d4e4f4;
  resize: vertical;
  tab-size: 2;
  min-height: 120px;
  white-space: pre;
  overflow: auto;
  overscroll-behavior: contain;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
}
.rl-code-input:focus {
  outline: none;
  border-color: rgba(56, 189, 248, 0.45);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04), 0 0 0 2px rgba(56, 189, 248, 0.12);
}
.rl-hint code {
  font-family: var(--rl-font-mono);
  font-size: 10px;
  color: var(--rl-accent);
}
.rl-tinker-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.rl-tinker-actions button {
  font-size: 11px;
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid var(--rl-border-strong);
  background: var(--rl-surface-2);
  color: var(--rl-text);
  cursor: pointer;
}
.rl-tinker-actions button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.rl-tinker-actions button.ghost {
  background: transparent;
  color: var(--rl-muted);
}
.rl-run-btn {
  background: var(--rl-accent-dim) !important;
  border-color: rgba(56, 189, 248, 0.4) !important;
  color: var(--rl-accent) !important;
  font-weight: 600;
}
.rl-run-result {
  margin-top: 4px;
}
.rl-run-meta {
  font-size: 10px;
  color: var(--rl-muted);
  margin-bottom: 4px;
}
.rl-run-meta .auth-hint {
  color: var(--rl-green);
}
.rl-run-meta .auth-hint.warn {
  color: #e0b341;
}
.rl-null-hint {
  margin: 0 0 8px;
  font-size: 11px;
  color: var(--rl-muted);
  line-height: 1.45;
}
.rl-null-hint code {
  font-family: var(--rl-font-mono);
  color: var(--rl-accent);
}
.rl-result-head {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--rl-muted);
  margin: 6px 0 4px;
}
.rl-result-head .type-tag {
  font-weight: 500;
  text-transform: none;
  letter-spacing: 0;
  color: var(--rl-accent);
  margin-left: 4px;
}
.rl-out-block.stdout {
  color: #94a3b8;
}
.rl-out-block {
  margin: 0 0 6px;
  padding: 8px 10px;
  border-radius: 6px;
  background: #0a0f14;
  font-family: var(--rl-font-mono);
  font-size: 10px;
  line-height: 1.45;
  color: #a8b8cc;
  /* No max-height / inner scroll — panel body (.rl-tinker-body) scrolls instead */
  overflow: visible;
}
.rl-out-block code {
  display: block;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: anywhere;
}
.rl-out-block.result {
  color: #86efac;
}

.rl-error.compact {
  margin: 0 0 6px;
  padding: 8px;
  font-size: 11px;
}
.rl-error .loc {
  opacity: 0.75;
  font-size: 10px;
}
.rl-user-list-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--rl-border);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--rl-muted);
}
.rl-user-list {
  list-style: none;
  margin: 6px 0 0;
  padding: 0;
}
.rl-user-list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 6px 8px;
  margin-bottom: 4px;
  border-radius: 6px;
  border: 1px solid var(--rl-border);
  background: var(--rl-surface);
}
.rl-user-list li.default {
  border-color: rgba(56, 189, 248, 0.35);
  background: var(--rl-accent-dim);
}
.rl-user-label {
  border: none;
  background: transparent;
  color: var(--rl-text);
  font-size: 12px;
  text-align: left;
  cursor: pointer;
  padding: 0;
}
.rl-default-tag {
  font-size: 9px;
  text-transform: uppercase;
  color: var(--rl-accent);
  margin-left: 6px;
}
.rl-user-actions {
  display: flex;
  gap: 2px;
}
.rl-user-actions button {
  border: none;
  background: transparent;
  color: var(--rl-muted);
  cursor: pointer;
  padding: 2px 4px;
  font-size: 12px;
}
.rl-user-actions button:hover {
  color: var(--rl-text);
}
.rl-user-actions button.danger:hover {
  color: var(--rl-red);
}
.rl-muted.small {
  padding: 8px 0;
  font-size: 11px;
}
</style>
