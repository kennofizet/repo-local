import { onMounted, onUnmounted } from 'vue'

const SCROLL_TARGET_SELECTOR =
  '.rl-scroll, .rl-scrollbar, .rl-detail-content, .rl-tinker-body, .rl-code-input'

const VISIBLE_CLASS = 'rl-scroll-visible'
const HIDE_AFTER_MS = 900

function isScrollTarget(el) {
  if (!el || el.nodeType !== 1) return false
  return el.matches(SCROLL_TARGET_SELECTOR)
}

function findScrollTarget(el) {
  if (!el || el.nodeType !== 1) return null
  if (isScrollTarget(el)) return el
  return el.closest(SCROLL_TARGET_SELECTOR)
}

/**
 * Hide scrollbars until hover, focus-within, or active scrolling (repo-local).
 * @param {import('vue').Ref<HTMLElement | null>} rootRef
 */
export function useAutoHideScrollbars(rootRef) {
  /** @type {WeakMap<Element, ReturnType<typeof setTimeout>>} */
  const hideTimers = new WeakMap()

  function show(el) {
    el.classList.add(VISIBLE_CLASS)
    const prev = hideTimers.get(el)
    if (prev) clearTimeout(prev)
    hideTimers.set(
      el,
      setTimeout(() => {
        el.classList.remove(VISIBLE_CLASS)
        hideTimers.delete(el)
      }, HIDE_AFTER_MS),
    )
  }

  function onScroll(e) {
    const el = findScrollTarget(e.target)
    if (el) show(el)
  }

  function onPointerOver(e) {
    const el = findScrollTarget(e.target)
    if (el) show(el)
  }

  function onFocusIn(e) {
    const el = findScrollTarget(e.target)
    if (el) el.classList.add(VISIBLE_CLASS)
  }

  function onFocusOut(e) {
    const el = findScrollTarget(e.target)
    if (!el) return
    const prev = hideTimers.get(el)
    if (prev) clearTimeout(prev)
    hideTimers.set(
      el,
      setTimeout(() => {
        if (!el.matches(':hover') && !el.contains(document.activeElement)) {
          el.classList.remove(VISIBLE_CLASS)
        }
        hideTimers.delete(el)
      }, HIDE_AFTER_MS),
    )
  }

  onMounted(() => {
    const root = rootRef.value
    if (!root) return
    root.addEventListener('scroll', onScroll, true)
    root.addEventListener('pointerover', onPointerOver, true)
    root.addEventListener('focusin', onFocusIn, true)
    root.addEventListener('focusout', onFocusOut, true)
  })

  onUnmounted(() => {
    const root = rootRef.value
    if (!root) return
    root.removeEventListener('scroll', onScroll, true)
    root.removeEventListener('pointerover', onPointerOver, true)
    root.removeEventListener('focusin', onFocusIn, true)
    root.removeEventListener('focusout', onFocusOut, true)
  })
}
