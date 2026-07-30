<script setup>
import { Button } from '@idds/vue'
import { IconPlus } from '@tabler/icons-vue'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useToastStore } from '../stores/toast'
import http from '../lib/http'
import AplikasiFormModal from '../components/AplikasiFormModal.vue'
import ConfirmationDrawer from '../components/ConfirmationDrawer.vue'
import DataTable from '../components/DataTable.vue'
import IddsSelect from '../components/IddsSelect.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import AsyncState from '../components/AsyncState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { usePengelolaNotifications } from '../composables/usePengelolaNotifications.js'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel, getStatusBadgeClass } from '../constants/status'

const toast = useToastStore()
const router = useRouter()


const apps = ref([])
const loading = ref(false)
const loadError = ref('')
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
  { value: 'all', label: 'Semua status', statuses: [] },
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
const statusGroupSelectOptions = STATUS_GROUP_OPTIONS.map(({ value, label }) => ({ value, label }))
const processStatusSelectOptions = [
  { value: 'all', label: 'Semua tahap' },
  ...PROCESS_STATUS_OPTIONS.map((status) => ({ value: status, label: getShortStatusLabel(status) })),
]

const hasActiveFilter = computed(() =>
  !!searchAplikasi.value?.trim()
  || filters.value.statusGroup !== 'all'
  || filters.value.processStatus !== 'all'
)

const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 30,
  total: 0,
})

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
  ])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

async function loadAplikasiData(page = 1) {
  loading.value = true
  loadError.value = ''
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
        perPage: Number(meta.per_page) || appsPagination.value.perPage || 30,
        total: Number(meta.total) || 0,
      }
    } else {
      apps.value = response.data || []
    }
  } catch (error) {
    warnDev('[PengelolaDashboard] loadAplikasiData error:', error)
    loadError.value = error.response?.data?.message || 'Koneksi ke server gagal. Silakan coba lagi.'
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

function statusToneClass(status) {
  const badgeClass = getStatusBadgeClass(status)
  if (badgeClass.includes('success')) return 'success'
  if (badgeClass.includes('danger')) return 'danger'
  if (badgeClass.includes('warning')) return 'warning'
  return ''
}

function selectStatusGroup(group) {
  filters.value.statusGroup = group
  applyStatusGroupFilter()
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
  <div class="ui-page">
    <PageHeader
      eyebrow="Pengelola Aplikasi"
      title="Kelola aplikasi"
      description="Pantau pengajuan, proses workflow, dan data aplikasi dalam satu ruang kerja."
    >
      <template #actions>
        <Button hierarchy="primary" size="lg" :prefix-icon="IconPlus" @click="openAddModal">
          Tambah aplikasi
        </Button>
      </template>
    </PageHeader>

    <div class="ui-page-content">
      <section class="ui-metric-grid" aria-label="Ringkasan aplikasi">
        <MetricCard
          label="Dalam pengembangan"
          :value="stats.development"
          icon="code"
          tone="amber"
          interactive
          :active="filters.statusGroup === 'development'"
          @select="selectStatusGroup('development')"
        />
        <MetricCard
          label="Operasional"
          :value="stats.operational"
          icon="check-circle"
          tone="green"
          interactive
          :active="filters.statusGroup === 'operational'"
          @select="selectStatusGroup('operational')"
        />
        <MetricCard
          label="Nonaktif"
          :value="stats.inactive"
          icon="alert-circle"
          tone="red"
          interactive
          :active="filters.statusGroup === 'inactive'"
          @select="selectStatusGroup('inactive')"
        />
        <MetricCard
          label="Perlu ditinjau"
          :value="pengajuanBaruCount"
          icon="inbox"
          tone="blue"
          interactive
          :active="filters.processStatus === 'diajukan'"
          @select="filters.processStatus = 'diajukan'; applyProcessFilter()"
        />
      </section>

      <section class="ui-panel" aria-labelledby="application-list-title">
        <header class="ui-panel-header">
          <h2 id="application-list-title">Daftar aplikasi</h2>
          <div class="ui-panel-actions">
            <SearchField
              v-model="searchAplikasi"
              label="Cari aplikasi"
              placeholder="Cari aplikasi"
              @update:model-value="scheduleSearch"
            />
            <IddsSelect
              v-model="filters.statusGroup"
              :options="statusGroupSelectOptions"
              accessible-label="Filter kategori status aplikasi"
              placeholder="Semua status"
              width="180px"
              @change="applyStatusGroupFilter"
            />
            <IddsSelect
              v-model="filters.processStatus"
              :options="processStatusSelectOptions"
              accessible-label="Filter tahap proses aplikasi"
              placeholder="Semua tahap"
              width="190px"
              @change="applyProcessFilter"
            />
          </div>
        </header>

        <AsyncState
          :loading="loading"
          :error="loadError"
          :empty="apps.length === 0"
          :empty-icon="hasActiveFilter ? 'search' : 'inbox'"
          :empty-title="hasActiveFilter ? 'Aplikasi tidak ditemukan' : 'Belum ada aplikasi'"
          :empty-description="hasActiveFilter
            ? 'Ubah kata kunci atau filter untuk menampilkan hasil lain.'
            : 'Aplikasi baru yang ditambahkan akan tampil di sini.'"
          @retry="loadAplikasiData(appsPagination.currentPage)"
        >
          <template v-if="hasActiveFilter" #action>
            <Button hierarchy="secondary" size="sm" @click="clearFilters">
              Hapus filter
            </Button>
          </template>

          <DataTable>
            <template #header>
              <tr>
                <th scope="col" class="col-num">#</th>
                <th scope="col">Nama aplikasi</th>
                <th scope="col">Kode unit</th>
                <th scope="col">Status</th>
                <th scope="col" class="ui-table-actions"><span class="sr-only">Aksi</span></th>
              </tr>
            </template>
            <template #body>
              <tr v-for="(app, idx) in apps" :key="app.id">
                <td data-label="Nomor" data-hide-mobile="true" class="col-num">{{ rowNumber(idx) }}</td>
                <td data-primary="true">
                  <RouterLink
                    class="ui-table-link"
                    :to="{ name: 'pengelola-aplikasi-app-detail', params: { id: app.id } }"
                  >
                    {{ app.nama_aplikasi }}
                  </RouterLink>
                  <span class="ui-table-subtitle">{{ app.nama_layanan }} · {{ app.nama_singkat }}</span>
                </td>
                <td data-label="Kode unit">{{ app.kode_unitOrganisasi || '-' }}</td>
                <td data-label="Status">
                  <StatusBadge :tone="statusToneClass(app.status)">
                    {{ getShortStatusLabel(app.status) }}
                  </StatusBadge>
                </td>
                <td class="ui-table-actions">
                  <IconActionCell :label="`Aksi untuk ${app.nama_aplikasi}`">
                    <IconActionButton label="Lihat detail" icon="eye" @click="viewDetail(app.id)" />
                    <IconActionButton label="Edit aplikasi" icon="edit" @click="openEditModal(app)" />
                    <IconActionButton label="Hapus aplikasi" icon="trash" tone="danger" @click="confirmDeleteApp(app)" />
                  </IconActionCell>
                </td>
              </tr>
            </template>
          </DataTable>
        </AsyncState>

        <PaginationBar
          :page="appsPagination.currentPage"
          :last-page="appsPagination.lastPage"
          :total="appsPagination.total"
          @change="changePage"
        />
      </section>
    </div>

    <AplikasiFormModal 
      :show="showAppModal" 
      :app="editingApp"
      @close="showAppModal = false"
      @saved="onAppSaved"
    />

    <ConfirmationDrawer
      v-model="showDeleteModal"
      title="Hapus aplikasi permanen"
      description="Dokumen, checklist, catatan, RFC, konfigurasi, notifikasi, dan riwayat status akan ikut dihapus dan tidak dapat dipulihkan."
      :subject="deleteTarget?.nama_aplikasi || 'Aplikasi'"
      confirm-label="Hapus permanen"
      :loading="deletingApp"
      @confirm="deleteApp"
      @cancel="closeDeleteModal"
    />

  </div>
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
  border-radius: 8px;
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

.stat-icon-wrap.bg-blue { background: #eff6ff; color: #1d4ed8; }
.stat-icon-wrap.bg-green { background: #f0fdf4; color: #047857; }
.stat-icon-wrap.bg-amber { background: #fffbeb; color: #92400e; }
.stat-icon-wrap.bg-red { background: #fef2f2; color: #b91c1c; }

.stat-label {
  font-size: var(--idds-caption-size);
  color: #6b7280;
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}

.stat-value {
  font-size: var(--idds-heading-h4-size);
  font-weight: var(--idds-weight-bold);
  color: #111827;
  line-height: var(--idds-heading-h4-line);
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

@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
  .stat-card { padding: 16px; }
  .stat-value { font-size: var(--idds-heading-h5-size); line-height: var(--idds-heading-h5-line); }
  .stat-label { font-size: var(--idds-caption-size); line-height: var(--idds-caption-line); }
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
  color: var(--ina-content-primary);
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
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.pengajuan-alert-text span {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  font-weight: var(--idds-weight-regular);
  line-height: var(--idds-caption-line);
}

/* ===== HERO CARD ===== */
.workspace-hero-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: var(--ina-background-primary);
  border-radius: 8px;
  padding: 24px 28px;
  margin: 0 20px 20px;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

.workspace-hero-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.ah-bc-link {
  background: none;
  border: none;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  padding: 0;
  font-size: var(--idds-caption-small-size);
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: color 0.15s;
  line-height: var(--idds-caption-small-line);
}

.ah-bc-link:hover { color: rgba(255, 255, 255, 0.9); }
.ah-bc-sep { color: rgba(255, 255, 255, 0.35); }
.ah-bc-current { color: rgba(255, 255, 255, 0.8); font-weight: var(--idds-weight-medium); }

.workspace-hero-title {
  margin: 0 0 4px;
  font-size: var(--idds-body-large-size);
  font-weight: var(--idds-weight-bold);
  color: #fff;
  line-height: var(--idds-body-large-line);
}

.workspace-hero-sub {
  margin: 0;
  font-size: var(--idds-caption-size);
  color: rgba(255, 255, 255, 0.7);
  line-height: var(--idds-caption-line);
}

/* ===== TABLE ===== */
.col-unit { white-space: nowrap; }

.col-aksi {
  width: 300px;
}

.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  font-weight: var(--idds-weight-semibold);
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.app-name-sub {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
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
