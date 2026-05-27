<template>
  <article class="rl-file-detail" :class="{ 'is-tinker-open': tinkerOpen }">
    <header class="rl-file-detail-head">
      <code v-if="filePath" class="path" :title="filePath">{{ filePath }}</code>
      <slot name="head-extra" />
      <div class="rl-file-detail-actions">
        <button
          v-if="showTinkerToggle"
          type="button"
          class="rl-tinker-toggle"
          :class="{ active: tinkerOpen }"
          title="Tinker query / test users"
          @click="$emit('toggle-tinker')"
        >
          <span class="rl-tinker-toggle-icon" aria-hidden="true">▸</span>
          {{ toggleLabel }}
        </button>
        <slot name="head-actions" />
      </div>
    </header>

    <div class="rl-detail-body">
      <div
        ref="contentRef"
        class="rl-detail-content rl-scrollbar"
        @mouseup="onContentMouseUp"
      >
        <slot />
      </div>

      <component
        :is="panelComponent"
        v-if="tinkerOpen && projectId && panelComponent"
        class="rl-detail-tinker"
        :project-id="projectId"
        :api="api"
        :selection-text="selectionText"
        :users="users"
        :default-entry-id="defaultEntryId"
        :default-user="defaultUser"
        :resolve-code="resolveCode"
        :query-draft="queryDraft"
        :query-dirty="queryDirty"
        :detail-key="detailKey"
        :inner-tab="innerTab"
        :display-label="displayLabel"
        @close="$emit('close-tinker')"
        @update:resolve-code="$emit('update:resolveCode', $event)"
        @update:query-draft="$emit('update:queryDraft', $event)"
        @query-user-edit="$emit('query-user-edit')"
        @update:inner-tab="$emit('update:innerTab', $event)"
        @add-user="$emit('add-user', $event)"
        @remove-user="$emit('remove-user', $event)"
        @set-default="$emit('set-default', $event)"
      />
    </div>
  </article>
</template>

<script>
import { ref } from 'vue'

export default {
  name: 'DetailFilePane',
  props: {
    filePath: { type: String, default: '' },
    showTinkerToggle: { type: Boolean, default: true },
    tinkerOpen: { type: Boolean, default: false },
    panelComponent: { type: [Object, Function, String], default: null },
    toggleLabel: { type: String, default: 'Support' },
    projectId: { type: String, default: '' },
    api: { type: Object, default: null },
    selectionText: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    defaultEntryId: { type: String, default: null },
    defaultUser: { type: Object, default: null },
    resolveCode: { type: String, default: '' },
    queryDraft: { type: String, default: '' },
    queryDirty: { type: Boolean, default: false },
    detailKey: { type: String, default: '' },
    innerTab: { type: String, default: 'query' },
    displayLabel: { type: Function, required: true },
  },
  emits: [
    'toggle-tinker',
    'close-tinker',
    'text-selected',
    'update:resolveCode',
    'update:queryDraft',
    'query-user-edit',
    'update:innerTab',
    'add-user',
    'remove-user',
    'set-default',
  ],
  setup(props, { emit }) {
    const contentRef = ref(null)

    function onContentMouseUp() {
      const sel = window.getSelection()
      const text = sel?.toString()?.trim() || ''
      if (!text) return
      const root = contentRef.value
      if (!root || !sel.anchorNode || !root.contains(sel.anchorNode)) return
      emit('text-selected', text)
    }

    return { contentRef, onContentMouseUp }
  },
}
</script>

<style scoped>
.rl-file-detail {
  flex: 1;
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--rl-surface);
}
.rl-file-detail-head {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--rl-border);
  background: linear-gradient(180deg, var(--rl-surface-2) 0%, var(--rl-surface) 100%);
}
.rl-file-detail-head .path {
  flex: 1;
  min-width: 0;
  font-family: var(--rl-font-mono);
  font-size: 12px;
  color: var(--rl-accent);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rl-file-detail-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  margin-left: auto;
}
.rl-file-detail-actions :deep(.rl-mode-badge) {
  flex-shrink: 0;
}
.rl-file-detail-head :deep(.rl-mode-badge) {
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
.rl-file-detail-head :deep(.rl-mode-badge.alt) {
  background: rgba(210, 153, 34, 0.15);
  color: #e0b341;
  border-color: rgba(210, 153, 34, 0.35);
}
.rl-tinker-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 600;
  padding: 5px 12px;
  border-radius: 8px;
  border: 1px solid var(--rl-border-strong);
  background: rgba(15, 23, 42, 0.6);
  color: var(--rl-muted);
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.rl-tinker-toggle-icon {
  font-size: 10px;
  opacity: 0.7;
  transition: transform 0.15s;
}
.rl-tinker-toggle.active .rl-tinker-toggle-icon {
  transform: rotate(90deg);
  opacity: 1;
}
.rl-tinker-toggle:hover,
.rl-tinker-toggle.active {
  color: var(--rl-accent);
  border-color: rgba(56, 189, 248, 0.45);
  background: var(--rl-accent-dim);
  box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.12);
}

.rl-detail-body {
  flex: 1;
  min-height: 0;
  min-width: 0;
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  overflow: hidden;
}
/* When support panel is open: content column shrinks; panel keeps fixed width */
.rl-file-detail.is-tinker-open .rl-detail-body {
  grid-template-columns: minmax(0, 1fr) clamp(280px, 36vw, 400px);
}
.rl-detail-content {
  min-width: 0;
  min-height: 0;
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 12px;
  background: #0a0f14;
  user-select: text;
}
/* Wide file/diff: grow past column width; horizontal scroll on .rl-detail-content */
.rl-detail-content :deep(.diff-viewer),
.rl-detail-content :deep(.rl-code) {
  margin: 0;
  padding: 0;
  display: block;
  width: max-content;
  min-width: 100%;
  max-width: none;
  overflow: visible;
}
.rl-detail-content :deep(.diff-viewer code),
.rl-detail-content :deep(.rl-code code) {
  display: block;
  white-space: pre;
  width: max-content;
  min-width: 100%;
}
.rl-detail-content :deep(.diff-line) {
  white-space: pre;
}
.rl-file-detail.is-tinker-open .rl-detail-content {
  border-right: 1px solid var(--rl-border);
}
.rl-detail-tinker {
  min-width: 0;
  min-height: 0;
  width: 100%;
  max-width: 100%;
  overflow: hidden;
}
</style>
