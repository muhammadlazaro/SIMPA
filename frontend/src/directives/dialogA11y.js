const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

let bodyLockCount = 0
let previousBodyOverflow = ''

function lockBody() {
  if (bodyLockCount === 0) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.body.classList.add('ui-modal-open')
  }
  bodyLockCount += 1
}

function unlockBody() {
  bodyLockCount = Math.max(0, bodyLockCount - 1)
  if (bodyLockCount === 0) {
    document.body.style.overflow = previousBodyOverflow
    document.body.classList.remove('ui-modal-open')
  }
}

function getFocusableElements(element) {
  return [...element.querySelectorAll(FOCUSABLE_SELECTOR)].filter((candidate) => {
    const style = window.getComputedStyle(candidate)
    return style.visibility !== 'hidden' && style.display !== 'none'
  })
}

export const dialogA11y = {
  mounted(element, binding) {
    const previousFocus = document.activeElement
    const state = {
      close: binding.value,
      previousFocus,
      keydown: null,
    }

    element.setAttribute('role', element.getAttribute('role') || 'dialog')
    element.setAttribute('aria-modal', 'true')

    state.keydown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault()
        if (typeof state.close === 'function') state.close()
        return
      }

      if (event.key !== 'Tab') return
      const focusable = getFocusableElements(element)
      if (focusable.length === 0) {
        event.preventDefault()
        element.focus()
        return
      }

      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }

    element.__dialogA11y = state
    element.addEventListener('keydown', state.keydown)
    lockBody()

    requestAnimationFrame(() => {
      const target = element.querySelector('[autofocus]') || getFocusableElements(element)[0]
      if (target) {
        target.focus({ preventScroll: true })
      } else {
        element.setAttribute('tabindex', '-1')
        element.focus({ preventScroll: true })
      }
    })
  },

  updated(element, binding) {
    if (element.__dialogA11y) {
      element.__dialogA11y.close = binding.value
    }
  },

  beforeUnmount(element) {
    const state = element.__dialogA11y
    if (!state) return

    element.removeEventListener('keydown', state.keydown)
    unlockBody()
    requestAnimationFrame(() => {
      if (state.previousFocus?.isConnected) {
        state.previousFocus.focus({ preventScroll: true })
      }
    })
    delete element.__dialogA11y
  },
}
