<script setup>
import { computed } from 'vue'
import { getShortStatusLabel, getStatusBadgeClass } from '../constants/status'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
  app: {
    type: Object,
    default: null,
  },
})

const statusTone = computed(() => {
  const badgeClass = getStatusBadgeClass(props.app?.status, 'badge-info')
  if (badgeClass.includes('success')) return 'success'
  if (badgeClass.includes('danger')) return 'danger'
  if (badgeClass.includes('warning')) return 'warning'
  return 'info'
})

/** Capitalise first letter of each word */
function titleCase(val) {
  if (!val) return '-'
  return val.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
  <div class="detail-grid">
    <div class="detail-item">
      <span class="detail-label">Nama layanan</span>
      <div>{{ app?.nama_layanan || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Nama singkat</span>
      <div>{{ app?.nama_singkat || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Nama aplikasi</span>
      <div>{{ app?.nama_aplikasi || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Jenis layanan</span>
      <div>{{ titleCase(app?.jenis_layanan_aplikasi) }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Kode unit organisasi</span>
      <div>{{ app?.kode_unitOrganisasi || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Tipe akuisisi</span>
      <div>{{ titleCase(app?.tipe_akuisisi) }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Status</span>
      <div>
        <StatusBadge :tone="statusTone">
          {{ getShortStatusLabel(app?.status) }}
        </StatusBadge>
      </div>
    </div>
  </div>
</template>
