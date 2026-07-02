import { defineStore } from 'pinia'

export const useToastStore = defineStore('toast', {
  state: () => ({ items: [] }),
  actions: {
    /**
     * Tampilkan toast notification.
     * @param {string} message - Pesan yang ditampilkan
     * @param {'info'|'success'|'error'|'warning'} type - Tipe toast
     * @param {number} timeoutMs - Durasi sebelum auto-dismiss (0 = persist)
     */
    push(message, type = 'info', timeoutMs = 3000) {
      const id = Date.now() + Math.random()
      this.items.push({ id, message, type })
      if (timeoutMs > 0) {
        setTimeout(() => this.remove(id), timeoutMs)
      }
    },
    remove(id) {
      this.items = this.items.filter(t => t.id !== id)
    },
    /** Hapus semua toast aktif. */
    clear() {
      this.items = []
    },
  }
})
