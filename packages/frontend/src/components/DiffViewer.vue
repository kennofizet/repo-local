<template>
  <pre class="diff-viewer"><code>
    <span
      v-for="(line, i) in lines"
      :key="i"
      class="diff-line"
      :class="lineClass(line)"
    >{{ line || ' ' }}</span>
  </code></pre>
</template>

<script>
export default {
  name: 'DiffViewer',
  props: {
    patch: { type: String, default: '' },
  },
  computed: {
    lines() {
      return (this.patch || '').split('\n')
    },
  },
  methods: {
    lineClass(line) {
      if (line.startsWith('+++') || line.startsWith('---') || line.startsWith('diff ')) return 'meta'
      if (line.startsWith('@@')) return 'hunk'
      if (line.startsWith('+')) return 'add'
      if (line.startsWith('-')) return 'del'
      return 'ctx'
    },
  },
}
</script>

<style scoped>
.diff-viewer {
  margin: 0;
  padding: 0;
  overflow: auto;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 20px;
  background: var(--rl-bg-subtle);
  border: 1px solid var(--rl-border);
  border-radius: 6px;
}
.diff-line {
  display: block;
  padding: 0 12px;
  white-space: pre;
}
.diff-line.add { background: rgba(63, 185, 80, 0.15); color: var(--rl-green); }
.diff-line.del { background: rgba(248, 81, 73, 0.15); color: var(--rl-red); }
.diff-line.hunk { color: var(--rl-accent); background: rgba(68, 147, 248, 0.08); }
.diff-line.meta { color: var(--rl-muted); }
.diff-line.ctx { color: var(--rl-text); }
</style>
