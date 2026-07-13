<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useToastStore } from '../stores/toast'
import http from '../lib/http'
import PengelolaLayout from '../layouts/PengelolaLayout.vue'
import AplikasiFormModal from '../components/AplikasiFormModal.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import Icons from '../components/Icons.vue'
import { usePengelolaNotifications } from '../composables/usePengelolaNotifications.js'
import { usePagination } from '../composables/usePagination.js'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel, getStatusBadgeClass } from '../constants/status'

const toast = useToastStore()
const router = useRouter()


const apps = ref([])
const loading = ref(false)
const searchAplikasi = ref('')
const filters = ref({
  statusGroup: 'all',
  processStatus: 'all',
})

const DEVELOPMENT_STATUSES = [
  'diajukan',
  'perlu_perbaikan_pengajuan',
  'terverifikasi',
  'layak',
  'analisa_desain',
  'pengembangan',
  'uat',
  'perbaikan_uat',
  'uji_keamanan',
  'perbaikan_keamanan',
  'siap_deploy',
  'deployed_staging',
]
const OPERATIONAL_STATUSES = ['deployed_production']
const INACTIVE_STATUSES = ['nonaktif']
const STOPPED_STATUSES = ['ditolak', 'tidak_layak']

const STATUS_GROUP_OPTIONS = [
  { value: 'all', label: 'Semua Status', statuses: [] },
  { value: 'development', label: 'Development', statuses: DEVELOPMENT_STATUSES },
  { value: 'operational', label: 'Operasional', statuses: OPERATIONAL_STATUSES },
  { value: 'inactive', label: 'Nonaktif', statuses: INACTIVE_STATUSES },
]

const PROCESS_STATUS_OPTIONS = [
  ...DEVELOPMENT_STATUSES.slice(0, 4),
  ...STOPPED_STATUSES,
  ...DEVELOPMENT_STATUSES.slice(4),
  ...OPERATIONAL_STATUSES,
  ...INACTIVE_STATUSES,
]

const hasActiveFilter = computed(() =>
  !!searchAplikasi.value?.trim()
  || filters.value.statusGroup !== 'all'
  || filters.value.processStatus !== 'all'
)

const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0,
})

const { pageNumbers: appPageNumbers } = usePagination(appsPagination)

const showAppModal = ref(false)
const editingApp = ref(null)
const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deletingApp = ref(false)

const stats = ref({ development: 0, operational: 0, inactive: 0, stopped: 0 })

const {
  count: pengajuanBaruCount,
  loadPengelolaNotifications,
} = usePengelolaNotifications()

let searchTimer = null

async function loadGlobalStats() {
  try {
    const resp = await http.get('/aplikasi/stats')
    const data = resp.data?.data || {}
    stats.value = {
      development: data.development || 0,
      operational: data.operational || 0,
      inactive: data.inactive || 0,
      stopped: data.stopped || 0,
    }
  } catch (error) {
    warnDev('[PengelolaDashboard] loadGlobalStats error:', error)
    stats.value = { development: 0, operational: 0, inactive: 0, stopped: 0 }
  }
}

onMounted(async () => {
  await Promise.all([
    loadAplikasiData(),
    loadGlobalStats(),
    loadPengelolaNotifications(),
  ])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

async function loadAplikasiData(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams({
      per_page: String(appsPagination.value.perPage),
      page: String(page),
    })
    const search = searchAplikasi.value?.trim()
    const statusFilter = getStatusFilterValue()

    if (search) params.set('q', search)
    if (statusFilter) params.set('status', statusFilter)

    const response = await http.get(`/aplikasi?${params.toString()}`)

    if (response.data.data) {
      const meta = response.data.meta || response.data
      apps.value = response.data.data
      appsPagination.value = {
        currentPage: Number(meta.current_page) || page,
        lastPage: Number(meta.last_page) || 1,
        perPage: Number(meta.per_page) || appsPagination.value.perPage || 10,
        total: Number(meta.total) || 0,
      }
    } else {
      apps.value = response.data || []
    }
  } catch (error) {
    warnDev('[PengelolaDashboard] loadAplikasiData error:', error)
    toast.push('Gagal memuat data aplikasi', 'error')
  } finally {
    loading.value = false
  }
}

function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadAplikasiData(1)
  }, 350)
}

function getStatusFilterValue() {
  if (filters.value.processStatus !== 'all') return filters.value.processStatus

  const group = STATUS_GROUP_OPTIONS.find((item) => item.value === filters.value.statusGroup)
  return group?.statuses?.length ? group.statuses.join(',') : ''
}

function getGroupCount(groupValue) {
  if (groupValue === 'development') return stats.value.development
  if (groupValue === 'operational') return stats.value.operational
  if (groupValue === 'inactive') return stats.value.inactive
  if (groupValue === 'stopped') return stats.value.stopped

  return stats.value.development + stats.value.operational + stats.value.inactive + stats.value.stopped
}

function applyStatusGroupFilter() {
  filters.value.processStatus = 'all'
  loadAplikasiData(1)
}

function applyProcessFilter() {
  if (filters.value.processStatus !== 'all') {
    filters.value.statusGroup = 'all'
  }
  loadAplikasiData(1)
}

function clearFilters() {
  searchAplikasi.value = ''
  filters.value = {
    statusGroup: 'all',
    processStatus: 'all',
  }
  loadAplikasiData(1)
}

function openAddModal() {
  editingApp.value = null
  showAppModal.value = true
}

async function openEditModal(app) {
  try {
    const resp = await http.get(`/aplikasi/${app.id}`)
    editingApp.value = resp.data.data || resp.data || app
  } catch {
    toast.push('Gagal memuat data terbaru, menggunakan data lokal.', 'warning')
    editingApp.value = app
  }
  showAppModal.value = true
}

function confirmDeleteApp(app) {
  deleteTarget.value = app
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deletingApp.value) return
  showDeleteModal.value = false
  deleteTarget.value = null
}

async function deleteApp() {
  if (!deleteTarget.value || deletingApp.value) return

  deletingApp.value = true
  try {
    await http.delete(`/aplikasi/${deleteTarget.value.id}`)
    toast.push('Aplikasi dan seluruh data terkait berhasil dihapus permanen.', 'success')
    showDeleteModal.value = false
    deleteTarget.value = null
    await loadAplikasiData(appsPagination.value.currentPage)
    await loadGlobalStats()
    await loadPengelolaNotifications()
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal menghapus aplikasi'
    toast.push(message, 'error')
    warnDev('[PengelolaDashboard] deleteApp error:', error)
  } finally {
    deletingApp.value = false
  }
}

function changePage(page) {
  loadAplikasiData(page)
}

function rowNumber(idx) {
  const start = ((appsPagination.value.currentPage - 1) * appsPagination.value.perPage) + 1
  return start + idx
}

function viewDetail(appId) {
  router.push({ name: 'pengelola-aplikasi-app-detail', params: { id: appId } })
}

async function onAppSaved() {
  showAppModal.value = false
  await loadAplikasiData()
  await loadGlobalStats()
  await loadPengelolaNotifications()
}

</script>

<template>
  <PengelolaLayout>
    <div class="container workspace-dashboard">
      <!-- Hero card (breadcrumb terintegrasi) -->
      <div class="workspace-hero-card">
        <div class="workspace-hero-text">
          <nav class="workspace-hero-breadcrumb" aria-label="breadcrumb">
            <button @click="router.push('/pengelola-aplikasi')" class="ah-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="ah-bc-sep">/</span>
            <span class="ah-bc-current">Kelola Aplikasi</span>
          </nav>
          <h2 class="workspace-hero-title">Kelola Aplikasi</h2>
          <p class="workspace-hero-sub">Pantau, kelola, dan proses seluruh pengajuan aplikasi dari unit kerja.</p>
        </div>
      </div>

    <!-- Aplikasi Section -->
    <div class="content-section active">
      <!-- Stats modern style -->
      <div class="stats-grid">
        <!-- Development -->
        <div class="stat-card dev">
          <div class="stat-header">
            <span class="stat-label">Development</span>
            <div class="stat-icon-wrap bg-amber">
              <Icons name="code" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.development }}</div>
        </div>

        <!-- Operasional -->
        <div class="stat-card production">
          <div class="stat-header">
            <span class="stat-label">Operasional</span>
            <div class="stat-icon-wrap bg-green">
              <Icons name="check-circle" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.operational }}</div>
        </div>

        <!-- Nonaktif -->
        <div class="stat-card maintenance">
          <div class="stat-header">
            <span class="stat-label">Nonaktif</span>
            <div class="stat-icon-wrap bg-red">
              <Icons name="alert-circle" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.inactive }}</div>
        </div>

      </div>

      <div class="card">
        <DataCardHead title="Daftar Aplikasi">
          <template #actions>
            <div class="search-group">
              <span class="search-icon">
                <Icons name="search" :size="16" />
              </span>
              <input 
                type="text" 
                v-model="searchAplikasi" 
                @input="scheduleSearch"
                placeholder="Cari aplikasi"
                maxlength="50" 
                aria-label="Cari aplikasi"
              />
            </div>
            <select
              v-model="filters.statusGroup"
              class="filter-select compact"
              aria-label="Filter kategori status aplikasi"
              @change="applyStatusGroupFilter"
            >
              <option v-for="group in STATUS_GROUP_OPTIONS" :key="group.value" :value="group.value">
                {{ group.label }}
              </option>
            </select>
            <select
              v-model="filters.processStatus"
              class="filter-select compact process"
              aria-label="Filter proses aplikasi"
              @change="applyProcessFilter"
            >
              <option value="all">Tahap Proses</option>
              <option v-for="status in PROCESS_STATUS_OPTIONS" :key="status" :value="status">
                {{ getShortStatusLabel(status) }}
              </option>
            </select>
            <button class="btn btn-primary" @click="openAddModal">
              <Icons name="plus" :size="16" />
              Tambah Aplikasi
            </button>
          </template>
        </DataCardHead>
        
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Memuat data aplikasi...</p>
        </div>
        <div v-else-if="apps.length === 0 && hasActiveFilter" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="search" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Tidak Ada Hasil</h3>
          <p class="global-empty-text">
            Tidak ada aplikasi yang cocok dengan filter ini.
          </p>
          <button type="button" class="btn btn-secondary" @click="clearFilters">
            Hapus filter
          </button>
        </div>
        <div v-else-if="apps.length === 0" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="inbox" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Belum Ada Aplikasi</h3>
          <p class="global-empty-text">
            Belum ada aplikasi yang diajukan atau dikelola dalam sistem ini.
          </p>
          <button @click="openAddModal" class="btn btn-primary">
            <Icons name="plus" :size="16" />
            Tambah Aplikasi
          </button>
        </div>
        <div v-else>
          <DataTable>
            <thead>
              <tr>
                <th scope="col" class="col-num">#</th>
                <th scope="col">Nama Aplikasi</th>
                <th scope="col">Kode Unit</th>
                <th scope="col">Status</th>
                <th scope="col" class="col-aksi">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(app, idx) in apps" :key="app.id" class="data-table-row is-clickable" @click="viewDetail(app.id)">
                <td class="col-num">{{ rowNumber(idx) }}</td>
                <td>
                  <div class="app-name-cell">
                    <span class="app-name-main">{{ app.nama_aplikasi }}</span>
                    <span class="app-name-sub">{{ app.nama_layanan }} - {{ app.nama_singkat }}</span>
                  </div>
                </td>
                <td class="col-unit">{{ app.kode_unitOrganisasi }}</td>
                <td>
                  <span :class="['badge', getStatusBadgeClass(app.status)]">
                    {{ getShortStatusLabel(app.status) }}
                  </span>
                </td>
                <td @click.stop>
                  <div class="action-group">
                    <button type="button" class="action-btn table-action-btn view-btn" @click="viewDetail(app.id)">
                      <Icons name="eye" :size="14" />
                      Detail
                    </button>
                    <button type="button" class="action-btn table-action-btn edit-btn" @click="openEditModal(app)">
                      <Icons name="edit" :size="14" />
                      Edit
                    </button>
                    <button type="button" class="action-btn table-action-btn delete-btn" @click="confirmDeleteApp(app)">
                      <Icons name="trash" :size="14" />
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </DataTable>
        </div>
        
        <!-- Pagination Controls -->
        <div v-if="appsPagination.lastPage > 1" class="pagination">
          <div class="pagination-info">
            Menampilkan {{ ((appsPagination.currentPage - 1) * appsPagination.perPage) + 1 }} - 
            {{ Math.min(appsPagination.currentPage * appsPagination.perPage, appsPagination.total) }} 
            dari {{ appsPagination.total }} data
          </div>
          <div class="pagination-controls">
            <button 
              @click="changePage(appsPagination.currentPage - 1)" 
              :disabled="appsPagination.currentPage === 1"
              class="pagination-btn">
              <Icons name="chevron-left" :size="16" />
            </button>
            
            <button 
              v-for="page in appPageNumbers" 
              :key="page"
              @click="page !== '...' && changePage(page)"
              :class="['pagination-btn', { active: page === appsPagination.currentPage, disabled: page === '...' }]">
              {{ page }}
            </button>
            
            <button 
              @click="changePage(appsPagination.currentPage + 1)" 
              :disabled="appsPagination.currentPage === appsPagination.lastPage"
              class="pagination-btn">
              <Icons name="chevron-right" :size="16" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <AplikasiFormModal 
      :show="showAppModal" 
      :app="editingApp"
      @close="showAppModal = false"
      @saved="onAppSaved"
    />

    <dialog v-if="showDeleteModal" class="modal active" open aria-labelledby="delete-app-title" @click.self="closeDeleteModal">
      <div class="modal-content confirm-modal">
        <div class="confirm-header">
          <Icons name="alert" :size="48" class="confirm-icon" />
          <h3 id="delete-app-title">Hapus Aplikasi Permanen</h3>
        </div>
        <div class="confirm-body">
          <p>
            Aplikasi berikut beserta dokumen, checklist, catatan, RFC, konfigurasi, notifikasi, dan riwayat statusnya akan dihapus permanen.
          </p>
          <p class="confirm-target"><strong>{{ deleteTarget?.nama_aplikasi || '-' }}</strong></p>
          <p class="confirm-warning">Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <div class="confirm-actions">
          <button type="button" class="btn btn-secondary" :disabled="deletingApp" @click="closeDeleteModal">Batal</button>
          <button type="button" class="btn btn-danger" :disabled="deletingApp" @click="deleteApp">
            <Icons name="trash" :size="14" />
            {{ deletingApp ? 'Menghapus...' : 'Hapus Permanen' }}
          </button>
        </div>
      </div>
    </dialog>

    </div>
  </PengelolaLayout>
</template>

<style scoped>
/* Statistics Grid (copied to match WorkspaceHome style) */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin: 12px 20px 16px;
}

/* Stat Cards - Modern Enterprise Style */
.stat-card {
  position: relative;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  overflow: hidden;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
}

.stat-icon-wrap.bg-blue { background: #eff6ff; color: #3b82f6; }
.stat-icon-wrap.bg-green { background: #f0fdf4; color: #10b981; }
.stat-icon-wrap.bg-amber { background: #fffbeb; color: #f59e0b; }
.stat-icon-wrap.bg-red { background: #fef2f2; color: #ef4444; }

.stat-label {
  font-size: 14px;
  color: #6b7280;
  font-weight: 500;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #111827;
  line-height: 1.2;
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  opacity: 0.8;
}

.stat-card.total::after { background: #3b82f6; }
.stat-card.production::after { background: #10b981; }
.stat-card.dev::after { background: #f59e0b; }
.stat-card.maintenance::after { background: #ef4444; }

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  border-color: #d1d5db;
}

.filter-select:focus-visible {
  outline: 3px solid rgba(30, 64, 175, 0.2);
  outline-offset: 2px;
}

.filter-select {
  min-width: 168px;
  height: 44px;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  background: #ffffff;
  color: #334155;
  padding: 0 12px;
  font: inherit;
  font-size: 14px;
  font-weight: 400;
}

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
  .stat-card { padding: 16px; }
  .stat-value { font-size: 24px; }
  .stat-label { font-size: 13px; }
  .filter-select {
    width: 100%;
    min-width: 0;
  }
}

.pengajuan-alert {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 16px;
  border-radius: 8px;
  border: 1px solid rgba(235, 87, 87, 0.35);
  background: rgba(235, 87, 87, 0.08);
  color: var(--notion-text);
}

.pengajuan-alert svg {
  flex-shrink: 0;
  color: #c92a2a;
  margin-top: 2px;
}

.pengajuan-alert-text {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  line-height: 1.45;
}

.pengajuan-alert-text span {
  font-size: 13px;
  color: var(--notion-text-secondary);
  font-weight: 400;
}

/* ===== HERO CARD ===== */
.workspace-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: linear-gradient(135deg, #1e3a8a 0%, #2c4fa8 100%);
  border-radius: 14px;
  padding: 24px 28px;
  margin: 0 20px 20px;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.workspace-hero-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: 12px;
}

.ah-bc-link {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  padding: 0;
  font-size: 12px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.15s;
}

.ah-bc-link:hover { color: rgba(255, 255, 255, 0.9); }
.ah-bc-sep { color: rgba(255, 255, 255, 0.35); }
.ah-bc-current { color: rgba(255, 255, 255, 0.8); font-weight: 500; }

.workspace-hero-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  color: #fff;
}

.workspace-hero-sub {
  margin: 0;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.5;
}

/* ===== TABLE ===== */
.col-unit { white-space: nowrap; }

.col-aksi {
  width: 300px;
}

.action-group {
  flex-wrap: wrap;
}

.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  font-weight: 600;
  color: var(--notion-text);
  font-size: 14px;
}

.app-name-sub {
  font-size: 12px;
  color: var(--notion-text-secondary);
}

/* Alert alignment */
.pengajuan-alert {
  margin: 0 20px 16px;
}

@media (max-width: 768px) {
  .workspace-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
  }

  .data-card-head-actions {
    flex-direction: column;
  }
}
</style>
