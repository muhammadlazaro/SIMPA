<script setup>
import { computed } from 'vue'
import { getShortStatusLabel, getStatusBadgeClass } from '../constants/status'

const props = defineProps({
  app: {
    type: Object,
    default: null,
  },
})

const statusClass = computed(() =>
  getStatusBadgeClass(props.app?.status, 'badge-info')
)

/** Capitalise first letter of each word */
function titleCase(val) {
  if (!val) return '-'
  return val.replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
  <div class="detail-grid">
    <div class="detail-item">
      <span class="detail-label">Nama Layanan</span>
      <div>{{ app?.nama_layanan || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Nama Singkat</span>
      <div>{{ app?.nama_singkat || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Nama Aplikasi</span>
      <div>{{ app?.nama_aplikasi || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Jenis Layanan</span>
      <div>{{ titleCase(app?.jenis_layanan_aplikasi) }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Kode Unit Organisasi</span>
      <div>{{ app?.kode_unitOrganisasi || '-' }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Tipe Akuisisi</span>
      <div>{{ titleCase(app?.tipe_akuisisi) }}</div>
    </div>
    <div class="detail-item">
      <span class="detail-label">Status</span>
      <div>
        <span :class="['badge', statusClass]">
          {{ getShortStatusLabel(app?.status) }}
        </span>
      </div>
    </div>
  </div>
</template>
