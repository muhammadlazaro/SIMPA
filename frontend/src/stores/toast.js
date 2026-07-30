import { defineStore } from 'pinia'

let toastSequence = 0

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
      toastSequence += 1
      const id = `toast-${Date.now()}-${toastSequence}`
      // IDDS recommends one temporary toast at a time so feedback stays focused.
      this.items = [{ id, message, type }]
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
