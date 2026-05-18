export function formatBytes(bytes) {
  if (bytes == null) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function formatDate(ts) {
  if (!ts) return ''
  return new Date(ts * 1000).toLocaleString()
}

export function statusLabel(code) {
  const map = {
    M: 'Modified',
    A: 'Added',
    D: 'Deleted',
    R: 'Renamed',
    '?': 'Untracked',
    U: 'Updated',
  }
  return map[code] || code || ''
}

export function guessLanguage(name) {
  const ext = (name || '').split('.').pop()?.toLowerCase()
  const langs = {
    js: 'javascript',
    ts: 'typescript',
    vue: 'vue',
    php: 'php',
    json: 'json',
    md: 'markdown',
    css: 'css',
    scss: 'scss',
    html: 'html',
    yml: 'yaml',
    yaml: 'yaml',
    xml: 'xml',
    sql: 'sql',
    sh: 'shell',
  }
  return langs[ext] || 'plaintext'
}
