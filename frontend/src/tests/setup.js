import { afterEach } from 'vitest'

afterEach(() => {
  document.body.innerHTML = ''
  document.body.className = ''
})

if (!window.matchMedia) {
  window.matchMedia = () => ({
    matches: false,
    addEventListener() {},
    removeEventListener() {},
  })
}

if (!window.ResizeObserver) {
  window.ResizeObserver = class {
    observe() { return undefined }
    unobserve() { return undefined }
    disconnect() { return undefined }
  }
}
