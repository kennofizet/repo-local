/**
 * Strip git-diff / change-list prefixes from copied or selected lines.
 * @param {string} text
 * @returns {string}
 */
export function sanitizeSelectionForCode(text) {
  if (!text || typeof text !== 'string') return ''

  const lines = text.replace(/\r\n/g, '\n').split('\n')
  const out = []

  for (const raw of lines) {
    let line = raw
    if (/^(\+\+\+ |--- |diff --git )/.test(line)) {
      continue
    }
    if (/^@@/.test(line)) {
      continue
    }
    if (/^[+\- ]/.test(line)) {
      line = line.slice(1)
    }
    out.push(line)
  }

  return out.join('\n').trim()
}
