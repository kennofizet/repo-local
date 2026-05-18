<template>
  <div class="file-tree">
    <div v-if="loadingRoot" class="tree-muted">Loading…</div>
    <ul v-else class="tree-root">
      <TreeNode
        v-for="entry in rootEntries"
        :key="entry.path"
        :entry="entry"
        :depth="0"
      />
    </ul>
  </div>
</template>

<script>
import { inject, onMounted, provide, ref, watch } from 'vue'
import TreeNode from './TreeNode.vue'

export default {
  name: 'FileTree',
  components: { TreeNode },
  props: {
    projectId: { type: String, required: true },
    selectedPath: { type: String, default: '' },
  },
  emits: ['select-file'],
  setup(props, { emit }) {
    const api = inject('repoLocalApi')
    const cache = ref({ '': [] })
    const expanded = ref(new Set(['']))
    const loadingPaths = ref(new Set(['']))
    const loadingRoot = ref(false)

    const rootEntries = ref([])

    async function loadPath(path) {
      if (!props.projectId) return []
      loadingPaths.value = new Set([...loadingPaths.value, path])
      try {
        const data = await api.getTree(props.projectId, path)
        const entries = data.entries || []
        cache.value = { ...cache.value, [path]: entries }
        if (path === '') rootEntries.value = entries
        return entries
      } catch {
        cache.value = { ...cache.value, [path]: [] }
        if (path === '') rootEntries.value = []
        return []
      } finally {
        const next = new Set(loadingPaths.value)
        next.delete(path)
        loadingPaths.value = next
      }
    }

    function isExpanded(path) {
      return expanded.value.has(path)
    }

    async function toggleDir(path) {
      const next = new Set(expanded.value)
      if (next.has(path)) {
        next.delete(path)
      } else {
        next.add(path)
        if (!cache.value[path]) await loadPath(path)
      }
      expanded.value = next
    }

    function selectFile(path) {
      emit('select-file', path)
    }

    function reset() {
      cache.value = { '': [] }
      expanded.value = new Set([''])
      rootEntries.value = []
    }

    provide('fileTree', {
      cache,
      expanded,
      loadingPaths,
      isExpanded,
      toggleDir,
      selectFile,
      selectedPath: () => props.selectedPath,
    })

    watch(
      () => props.projectId,
      async (id) => {
        reset()
        if (!id) return
        loadingRoot.value = true
        await loadPath('')
        loadingRoot.value = false
      },
      { immediate: true },
    )

    onMounted(() => {
      if (props.projectId && !cache.value['']?.length) {
        loadPath('')
      }
    })

    return { rootEntries, loadingRoot, reset, refresh: () => loadPath('') }
  },
}
</script>

<style scoped>
.file-tree {
  height: 100%;
  min-height: 0;
}
.tree-root {
  list-style: none;
  margin: 0;
  padding: 6px 0;
}
.tree-muted {
  padding: 12px 14px;
  font-size: 12px;
  color: var(--rl-muted);
}
</style>
