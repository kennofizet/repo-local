<template>
  <li class="tree-node">
    <button
      type="button"
      class="tree-row"
      :class="{
        active: isSelected,
        dir: entry.type === 'dir',
        expanded: entry.type === 'dir' && expanded,
        loading: isLoading,
      }"
      :style="{ paddingLeft: `${10 + depth * 14}px` }"
      @click="onClick"
    >
      <span class="chev" :class="{ invisible: entry.type !== 'dir' }">
        <svg v-if="entry.type === 'dir'" width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
          <path v-if="expanded" d="M2 2 L5 5 L8 2 Z" />
          <path v-else d="M2 2 L5 5 L2 8 Z" />
        </svg>
      </span>
      <span class="icon" :class="entry.type">
        <svg v-if="entry.type === 'dir'" width="14" height="14" viewBox="0 0 16 16" fill="currentColor" opacity="0.85">
          <path d="M1.5 3.5A1 1 0 0 1 2.5 2.5h4l1.5 1.5H13.5A1 1 0 0 1 14.5 4.5v8a1 1 0 0 1-1 1H2.5a1 1 0 0 1-1-1v-8z"/>
        </svg>
        <svg v-else width="14" height="14" viewBox="0 0 16 16" fill="currentColor" opacity="0.7">
          <path d="M3 2h7l3 3v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
        </svg>
      </span>
      <span class="label">{{ entry.name }}</span>
      <span v-if="entry.git_status" class="status">{{ entry.git_status }}</span>
    </button>
    <ul v-if="entry.type === 'dir' && expanded" class="tree-children">
      <div v-if="isLoading" class="tree-loading" :style="{ paddingLeft: `${24 + depth * 14}px` }">…</div>
      <TreeNode
        v-for="child in children"
        :key="child.path"
        :entry="child"
        :depth="depth + 1"
      />
    </ul>
  </li>
</template>

<script>
import { computed, inject } from 'vue'

export default {
  name: 'TreeNode',
  props: {
    entry: { type: Object, required: true },
    depth: { type: Number, default: 0 },
  },
  setup(props) {
    const tree = inject('fileTree')

    const expanded = computed(() => tree.isExpanded(props.entry.path))
    const children = computed(() => tree.cache.value[props.entry.path] || [])
    const isLoading = computed(() => tree.loadingPaths.value.has(props.entry.path))
    const isSelected = computed(
      () => props.entry.type === 'file' && tree.selectedPath() === props.entry.path,
    )

    function onClick() {
      if (props.entry.type === 'dir') {
        tree.toggleDir(props.entry.path)
      } else {
        tree.selectFile(props.entry.path)
      }
    }

    return { expanded, children, isLoading, isSelected, onClick }
  },
}
</script>

<style scoped>
.tree-node {
  list-style: none;
}
.tree-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px 5px 0;
  margin: 1px 6px;
  border: 1px solid transparent;
  border-radius: 6px;
  background: transparent;
  color: var(--rl-text);
  font-size: 12px;
  cursor: pointer;
  text-align: left;
  transition: background 0.12s, border-color 0.12s;
}
.tree-row:hover {
  background: var(--rl-hover);
}
.tree-row.active {
  background: var(--rl-accent-dim);
  border-color: rgba(56, 189, 248, 0.35);
}
.tree-row.dir.expanded .icon {
  color: var(--rl-accent);
}
.chev {
  width: 12px;
  flex-shrink: 0;
  color: var(--rl-muted);
  display: flex;
  align-items: center;
  justify-content: center;
}
.chev.invisible {
  visibility: hidden;
}
.icon {
  flex-shrink: 0;
  display: flex;
  color: var(--rl-muted);
}
.icon.file {
  color: #94a3b8;
}
.label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-family: var(--rl-font-mono);
}
.status {
  font-size: 10px;
  font-weight: 600;
  color: var(--rl-green);
  background: rgba(52, 211, 153, 0.12);
  padding: 1px 5px;
  border-radius: 4px;
}
.tree-children {
  list-style: none;
  margin: 0;
  padding: 0;
}
.tree-loading {
  font-size: 11px;
  color: var(--rl-muted);
  padding: 4px 0 6px;
}
</style>
