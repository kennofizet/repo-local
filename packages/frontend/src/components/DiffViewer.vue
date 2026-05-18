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
  overflow: visible;
  font-family: var(--rl-font-mono, ui-monospace, Consolas, monospace);
  font-size: 11px;
  line-height: 20px;
  background: #0a0f14;
  border: 1px solid var(--rl-border, rgba(148, 163, 184, 0.12));
  border-radius: 8px;
}
.diff-line {
  display: block;
  padding: 0 12px;
  white-space: pre;
}
.diff-line.add {
  background: rgba(52, 211, 153, 0.12);
  color: var(--rl-green, #34d399);
}
.diff-line.del {
  background: rgba(248, 113, 113, 0.12);
  color: var(--rl-red, #f87171);
}
.diff-line.hunk {
  color: var(--rl-accent, #38bdf8);
  background: rgba(56, 189, 248, 0.08);
}
.diff-line.meta {
  color: var(--rl-muted, #7d8da6);
}
.diff-line.ctx {
  color: var(--rl-text, #e8eef6);
}
</style>
