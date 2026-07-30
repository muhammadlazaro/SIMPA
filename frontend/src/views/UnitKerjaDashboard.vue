<script setup>
import { Button } from '@idds/vue'
import { IconPlus } from '@tabler/icons-vue'
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import AplikasiFormModal from '../components/AplikasiFormModal.vue'
import ConfirmationDrawer from '../components/ConfirmationDrawer.vue'
import DataTable from '../components/DataTable.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import AsyncState from '../components/AsyncState.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel, getStatusBadgeClass, getStatusTooltip } from '../constants/status'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const apps = ref([])
const loading = ref(false)
const loadError = ref('')
const searchAplikasi = ref('')
const showAppModal = ref(false)
const withdrawing = ref(false)
const confirmWithdrawApp = ref(null)
let searchTimer = null

const hasActiveSearch = computed(() => !!searchAplikasi.value?.trim())

function handleWithdrawModalChange(open) {
  if (!open) cancelWithdraw()
}

const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 30,
  total: 0,
})

const statsData = ref({ development: 0, operational: 0, inactive: 0, stopped: 0 })
const stats = computed(() => ({
  total: Object.values(statsData.value).reduce((total, value) => total + Number(value || 0), 0),
  aktif: statsData.value.operational,
  proses: statsData.value.development,
  nonaktif: statsData.value.inactive,
}))

// Greeting berdasarkan waktu
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Selamat pagi'
  if (h < 17) return 'Selamat siang'
  return 'Selamat malam'
})

onMounted(async () => {
  await Promise.all([loadAplikasiData(), loadStats()])
})

onBeforeUnmount(() => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }
})

async function loadAplikasiData(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const searchQuery = searchAplikasi.value?.trim()
      ? `q=${encodeURIComponent(searchAplikasi.value.trim())}&`
      : ''
    const response = await http.get(
      `/aplikasi?${searchQuery}per_page=${appsPagination.value.perPage}&page=${page}`
    )

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
      appsPagination.value.currentPage = page
    }
  } catch (error) {
    warnDev('[UnitKerjaDashboard] loadAplikasiData error:', error)
    loadError.value = error.response?.data?.message || 'Daftar pengajuan belum dapat dimuat.'
  } finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    const response = await http.get('/aplikasi/stats')
    statsData.value = {
      development: Number(response.data?.data?.development) || 0,
      operational: Number(response.data?.data?.operational) || 0,
      inactive: Number(response.data?.data?.inactive) || 0,
      stopped: Number(response.data?.data?.stopped) || 0,
    }
  } catch (error) {
    warnDev('[UnitKerjaDashboard] loadStats error:', error)
  }
}

function openAddModal() {
  showAppModal.value = true
}

function closeAppModal() {
  showAppModal.value = false
}

async function onAppSaved() {
  showAppModal.value = false
  await Promise.all([
    loadAplikasiData(appsPagination.value.currentPage),
    loadStats(),
  ])
}

function clearSearch() {
  searchAplikasi.value = ''
  loadAplikasiData(1)
}

function scheduleSearch() {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }
  searchTimer = setTimeout(() => {
    loadAplikasiData(1)
  }, 350)
}

function viewDetail(appId) {
  router.push({ name: 'unit-kerja-app-detail', params: { id: appId } })
}

function askWithdraw(app) {
  confirmWithdrawApp.value = app
}

function cancelWithdraw() {
  confirmWithdrawApp.value = null
}

async function doWithdraw() {
  if (!confirmWithdrawApp.value) return
  withdrawing.value = true
  try {
    await http.delete(`/aplikasi/${confirmWithdrawApp.value.id}/withdraw`)
    toast.push('Pengajuan berhasil ditarik.', 'success')
    confirmWithdrawApp.value = null
    await Promise.all([
      loadAplikasiData(appsPagination.value.currentPage),
      loadStats(),
    ])
  } catch (error) {
    const msg = error.response?.data?.message || 'Gagal menarik pengajuan.'
    toast.push(msg, 'error')
  } finally {
    withdrawing.value = false
  }
}

function changePage(page) {
  loadAplikasiData(page)
}

function hasActiveUatDocument(app) {
  return app?.has_active_uat_document === true || app?.has_active_uat_document === 1 || app?.has_active_uat_document === '1'
}

function getNextActionText(appOrStatus) {
  const app = typeof appOrStatus === 'object' ? appOrStatus : null
  const status = app?.status || appOrStatus

  if (status === 'perlu_perbaikan_pengajuan') return 'Tindakan: Silakan perbaiki pengajuan'
  if (status === 'uat') {
    return hasActiveUatDocument(app)
      ? 'Menunggu verifikasi UAT oleh Pengelola Aplikasi'
      : 'Tindakan: Silakan unggah dokumen UAT'
  }
  if (status === 'perbaikan_uat') return 'Menunggu perbaikan UAT dari Tim Implementasi'

  return ''
}

function getNextActionClass(app) {
  if (!app) return ''
  if (app.status === 'uat' && hasActiveUatDocument(app)) return 'is-waiting'
  if (app.status === 'perbaikan_uat') return 'is-waiting'
  return ''
}

function statusToneClass(status) {
  const badgeClass = getStatusBadgeClass(status)
  if (badgeClass.includes('success')) return 'success'
  if (badgeClass.includes('danger')) return 'danger'
  if (badgeClass.includes('warning')) return 'warning'
  return ''
}

</script>

<template>
  <div class="ui-page">
    <PageHeader
      eyebrow="Unit Kerja"
      :title="`${greeting}, ${auth.user?.name?.split(' ')[0] || 'Pengguna'}`"
      description="Pantau pengajuan aplikasi dan tindak lanjuti UAT yang siap diuji."
    >
      <template #actions>
        <Button hierarchy="primary" size="lg" :prefix-icon="IconPlus" @click="openAddModal">
          Ajukan aplikasi
        </Button>
      </template>
    </PageHeader>

    <div class="ui-page-content">
      <section class="ui-metric-grid" aria-label="Ringkasan pengajuan">
        <MetricCard label="Total pengajuan" :value="stats.total" icon="file" tone="blue" />
        <MetricCard label="Operasional" :value="stats.aktif" icon="check-circle" tone="green" />
        <MetricCard label="Dalam proses" :value="stats.proses" icon="code" tone="amber" />
        <MetricCard label="Nonaktif" :value="stats.nonaktif" icon="alert-circle" tone="red" />
      </section>

      <section class="ui-panel" aria-labelledby="submission-list-title">
        <header class="ui-panel-header">
          <h2 id="submission-list-title">Pengajuan dan tindak lanjut UAT</h2>
          <div class="ui-panel-actions">
            <SearchField
              v-model="searchAplikasi"
              label="Cari pengajuan"
              placeholder="Cari aplikasi"
              @update:model-value="scheduleSearch"
            />
          </div>
        </header>

        <AsyncState
          :loading="loading"
          :error="loadError"
          :empty="apps.length === 0"
          :empty-icon="hasActiveSearch ? 'search' : 'folder-plus'"
          :empty-title="hasActiveSearch ? 'Pengajuan tidak ditemukan' : 'Belum ada pengajuan'"
          :empty-description="hasActiveSearch
            ? 'Coba kata kunci lain atau hapus pencarian.'
            : 'Pengajuan aplikasi baru Anda akan tampil di sini.'"
          @retry="loadAplikasiData(appsPagination.currentPage)"
        >
          <template v-if="hasActiveSearch" #action>
            <Button hierarchy="secondary" size="sm" @click="clearSearch">
              Hapus pencarian
            </Button>
          </template>

            <DataTable>
                <template #header>
                  <tr>
                    <th scope="col" class="col-num">#</th>
                    <th scope="col">Nama aplikasi</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tanggal pengajuan</th>
                    <th scope="col" class="ui-table-actions"><span class="sr-only">Aksi</span></th>
                  </tr>
                </template>
                <template #body>
                  <tr v-for="(app, idx) in apps" :key="app.id">
                    <td data-label="Nomor" data-hide-mobile="true" class="col-num">
                      {{ ((appsPagination.currentPage - 1) * appsPagination.perPage) + idx + 1 }}
                    </td>
                    <td data-primary="true">
                      <RouterLink
                        class="ui-table-link"
                        :to="{ name: 'unit-kerja-app-detail', params: { id: app.id } }"
                      >
                        {{ app.nama_aplikasi }}
                      </RouterLink>
                      <span class="ui-table-subtitle">{{ app.nama_layanan }}</span>
                    </td>
                    <td data-label="Status">
                      <div class="status-cell">
                        <StatusBadge :tone="statusToneClass(app.status)" :title="getStatusTooltip(app.status)">
                          {{ getShortStatusLabel(app.status) }}
                        </StatusBadge>
                        <div v-if="getNextActionText(app)" :class="['next-action-text', getNextActionClass(app)]">
                          {{ getNextActionText(app) }}
                        </div>
                      </div>
                    </td>
                    <td data-label="Diajukan" class="col-date">
                      {{ app.created_at ? new Date(app.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-' }}
                    </td>
                    <td class="ui-table-actions">
                      <IconActionCell :label="`Aksi untuk ${app.nama_aplikasi}`">
                        <IconActionButton label="Lihat progres" icon="eye" @click="viewDetail(app.id)" />
                        <IconActionButton
                          v-if="app.status === 'diajukan'"
                          label="Tarik pengajuan"
                          icon="trash"
                          tone="danger"
                          @click="askWithdraw(app)"
                        />
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

    <AplikasiFormModal :show="showAppModal" :app="null" @close="closeAppModal" @saved="onAppSaved" />

    <ConfirmationDrawer
      :model-value="!!confirmWithdrawApp"
      title="Tarik pengajuan"
      description="Pengajuan akan dibatalkan dan tidak lagi tampil sebagai pengajuan aktif."
      :subject="confirmWithdrawApp?.nama_aplikasi || 'Pengajuan aplikasi'"
      confirm-label="Tarik pengajuan"
      :loading="withdrawing"
      @update:model-value="handleWithdrawModalChange"
      @confirm="doWithdraw"
      @cancel="cancelWithdraw"
    />
  </div>
</template>

<style scoped>
/* ===== WELCOME CARD ===== */
.uk-welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: var(--ina-background-primary);
  border-radius: 8px;
  padding: 24px 28px;
  margin: 0 20px 28px;
  box-shadow: 0 4px 14px rgba(30, 58, 138, 0.18);
}

/* Breadcrumb inside welcome card */
.uk-welcome-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.uk-bc-link {
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

.uk-bc-link:hover {
  color: rgba(255, 255, 255, 0.9);
}

.uk-bc-sep {
  color: rgba(255, 255, 255, 0.35);
}

.uk-bc-current {
  color: rgba(255, 255, 255, 0.8);
  font-weight: var(--idds-weight-medium);
}

.uk-welcome-title {
  margin: 0 0 4px;
  font-size: var(--idds-body-large-size);
  font-weight: var(--idds-weight-bold);
  color: #fff;
  line-height: var(--idds-body-large-line);
}

.uk-welcome-sub {
  margin: 0;
  font-size: var(--idds-caption-size);
  color: rgba(255, 255, 255, 0.75);
  line-height: var(--idds-caption-line);
}

.uk-welcome-btn {
  flex-shrink: 0;
  padding: 10px 20px;
  font-size: var(--idds-caption-size);
  border-radius: 8px;
  background: #fff;
  color: #1e3a8a;
  font-weight: var(--idds-weight-semibold);
  border: none;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  line-height: var(--idds-caption-line);
}

.uk-welcome-btn:hover {
  background: #f0f4ff;
}

/* ===== STAT CARDS (sama dengan PengelolaDashboard) ===== */
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
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 8px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  background: transparent;
  transition: background 0.2s ease;
}

.stat-card.total::after { background: #3b82f6; }
.stat-card.production::after { background: #10b981; }
.stat-card.dev::after { background: #f59e0b; }
.stat-card.maintenance::after { background: #ef4444; }

.stat-card:hover {
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
  transform: translateY(-2px);
  border-color: rgba(0, 0, 0, 0.12);
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.stat-label {
  font-size: var(--idds-caption-size);
  color: #4b5563;
  font-weight: var(--idds-weight-semibold);
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-caption-line);
}

.stat-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.bg-blue { background: #eff6ff; color: #1d4ed8; }
.bg-green { background: #ecfdf5; color: #047857; }
.bg-amber { background: #fffbeb; color: #92400e; }
.bg-red { background: #fef2f2; color: #b91c1c; }

.stat-value {
  font-size: var(--idds-heading-h3-size);
  font-weight: var(--idds-weight-bold);
  color: #111827;
  line-height: var(--idds-heading-h3-line);
  letter-spacing: var(--idds-letter-spacing);
}

.col-date {
  white-space: nowrap;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-line);
}

.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  font-weight: var(--idds-weight-semibold);
  font-size: var(--idds-caption-size);
  color: var(--ina-content-primary);
  line-height: var(--idds-caption-line);
}

.app-name-sub {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.status-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

/* ===== PREMIUM EMPTY STATE ===== */
.global-empty {
  text-align: center;
  padding: 80px 24px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  margin: 20px;
}

.global-empty-icon-wrapper {
  width: 64px;
  height: 64px;
  margin: 0 auto 20px;
  background: #f3f4f6;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--idds-heading-h3-size);
  line-height: var(--idds-heading-h3-line);
}

.global-empty-title {
  font-size: var(--idds-body-size);
  font-weight: var(--idds-weight-bold);
  color: #111827;
  margin-bottom: 8px;
  line-height: var(--idds-body-line);
}

.global-empty-text {
  font-size: var(--idds-caption-size);
  color: #6b7280;
  max-width: 320px;
  margin: 0 auto 24px;
  line-height: var(--idds-caption-line);
}

.withdraw-btn {
  background: rgba(220, 38, 38, 0.07) !important;
  color: #b91c1c !important;
  border: 1px solid rgba(220, 38, 38, 0.25) !important;
}

.withdraw-btn:hover {
  background: rgba(220, 38, 38, 0.14) !important;
  border-color: rgba(220, 38, 38, 0.4) !important;
}

.btn-danger {
  flex: 1;
  background: #dc2626;
  color: #fff;
  border: none;
}

.btn-danger:hover:not(:disabled) {
  background: #b91c1c;
}

.btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.withdraw-confirm-modal {
  max-width: 400px !important;
}

.withdraw-confirm-body {
  padding: 4px 0 20px;
}

.withdraw-confirm-text {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-primary);
  line-height: var(--idds-caption-line);
  margin: 0 0 8px;
}

.withdraw-confirm-note {
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  margin: 0;
  line-height: var(--idds-caption-line);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .stat-card {
    padding: 20px 20px;
  }

  .stat-value {
    font-size: var(--idds-heading-h4-size);
    line-height: var(--idds-heading-h4-line);
  }

  .stat-label {
    font-size: var(--idds-caption-size);
    line-height: var(--idds-caption-line);
  }

  .uk-welcome-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .uk-welcome-btn {
    width: 100%;
    justify-content: center;
  }

  .uk-card-head {
    flex-direction: column;
    align-items: flex-start;
  }

}

@media (max-width: 480px) {
  .uk-welcome-title {
    font-size: var(--idds-body-size);
    line-height: var(--idds-body-line);
  }
}

.next-action-text {
  font-size: var(--idds-caption-small-size);
  color: #d97706;
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-small-line);
}

.next-action-text.is-waiting {
  color: #2563eb;
}
</style>
