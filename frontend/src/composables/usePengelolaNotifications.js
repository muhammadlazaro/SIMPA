import { ref } from 'vue'
import http from '../lib/http'

const count = ref(0)
const items = ref([])
const loading = ref(false)

/**
 * Shared state for pengelola: pengajuan baru dari unit kerja (status diajukan).
 */
export function usePengelolaNotifications() {
  async function loadPengelolaNotifications() {
    loading.value = true
    try {
      const r = await http.get('/aplikasi/pengelola-notifications')
      const d = r.data?.data || {}
      count.value = typeof d.count === 'number' ? d.count : 0
      items.value = Array.isArray(d.items) ? d.items : []
    } catch {
      count.value = 0
      items.value = []
    } finally {
      loading.value = false
    }
  }

  return { count, items, loading, loadPengelolaNotifications }
}
