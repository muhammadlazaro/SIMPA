<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import UserLayout from '../layouts/UserLayout.vue'
import AplikasiFormModal from '../components/AplikasiFormModal.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import Icons from '../components/Icons.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { getHomeByRole } from '../constants/roles'
import { usePagination } from '../composables/usePagination.js'
import { warnDev } from '../utils/logger'
import { getStatusBadgeClass, getStatusLabel, getStatusTooltip } from '../constants/status'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const apps = ref([])
const loading = ref(false)
const searchAplikasi = ref('')
const showAppModal = ref(false)
const withdrawing = ref(false)
const confirmWithdrawApp = ref(null)
let searchTimer = null

const basePath = computed(() => getHomeByRole(auth.role).path)
const hasActiveSearch = computed(() => !!searchAplikasi.value?.trim())

const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0,
})

const { pageNumbers: appPageNumbers } = usePagination(appsPagination)

// Stat cards data
const stats = computed(() => {
  const total = appsPagination.value.total
  const aktif = apps.value.filter(a => a.status === 'deployed_production').length
  const proses = apps.value.filter(a =>
    ['diajukan', 'perlu_perbaikan_pengajuan', 'terverifikasi', 'layak', 'analisa_desain', 'pengembangan', 'uat', 'perbaikan_uat', 'uji_keamanan', 'perbaikan_keamanan', 'siap_deploy', 'deployed_staging'].includes(a.status)
  ).length
  const nonaktif = apps.value.filter(a => a.status === 'nonaktif').length
  return { total, aktif, proses, nonaktif }
})

// Greeting berdasarkan waktu
const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Selamat pagi'
  if (h < 17) return 'Selamat siang'
  return 'Selamat malam'
})

onMounted(async () => {
  await loadAplikasiData()
})

onBeforeUnmount(() => {
  if (searchTimer) {
    clearTimeout(searchTimer)
  }
})

async function loadAplikasiData(page = 1) {
  loading.value = true
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
        perPage: Number(meta.per_page) || appsPagination.value.perPage || 10,
        total: Number(meta.total) || 0,
      }
    } else {
      apps.value = response.data || []
      appsPagination.value.currentPage = page
    }
  } catch (error) {
    warnDev('[UnitKerjaDashboard] loadAplikasiData error:', error)
    toast.push('Tidak dapat memuat daftar pengajuan.', 'error', 4000)
  } finally {
    loading.value = false
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
  await loadAplikasiData(appsPagination.value.currentPage)
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
    await loadAplikasiData(appsPagination.value.currentPage)
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

</script>

<template>
  <UserLayout>
    <div class="container uk-dashboard">
      <!-- Welcome header card (breadcrumb terintegrasi) -->
      <div class="uk-welcome-card">
        <div class="uk-welcome-text">
          <nav class="uk-welcome-breadcrumb" aria-label="breadcrumb">
            <button type="button" @click="router.push(basePath)" class="uk-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="uk-bc-sep">/</span>
            <span class="uk-bc-current">Pengajuan & UAT</span>
          </nav>
          <h2 class="uk-welcome-title">{{ greeting }}, {{ auth.user?.name?.split(' ')[0] || 'User' }}</h2>
          <p class="uk-welcome-sub">Pantau pengajuan aplikasi dan tindak lanjuti UAT yang sudah siap diuji.</p>
        </div>
      </div>

      <!-- Stat cards (Modern Enterprise Style) -->
      <div class="stats-grid">
        <div class="stat-card total">
          <div class="stat-header">
            <span class="stat-label">Total Item</span>
            <div class="stat-icon-wrap bg-blue">
              <Icons name="file" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
        <div class="stat-card production">
          <div class="stat-header">
            <span class="stat-label">Aktif / Deployed</span>
            <div class="stat-icon-wrap bg-green">
              <Icons name="check-circle" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.aktif }}</div>
        </div>
        <div class="stat-card dev">
          <div class="stat-header">
            <span class="stat-label">Dalam Proses</span>
            <div class="stat-icon-wrap bg-amber">
              <Icons name="code" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.proses }}</div>
        </div>
        <div class="stat-card maintenance">
          <div class="stat-header">
            <span class="stat-label">Nonaktif</span>
            <div class="stat-icon-wrap bg-red">
              <Icons name="alert-circle" :size="18" />
            </div>
          </div>
          <div class="stat-value">{{ stats.nonaktif }}</div>
        </div>
      </div>

      <!-- Main table card -->
      <div class="content-section active">
        <div class="card uk-card">
          <DataCardHead title="Pengajuan & Tindak Lanjut UAT">
            <template #actions>
              <div class="search-group">
                <span class="search-icon">
                  <Icons name="search" :size="16" />
                </span>
                <input
                  type="search"
                  v-model="searchAplikasi"
                  @input="scheduleSearch"
                  placeholder="Cari aplikasi..."
                  maxlength="50"
                  aria-label="Cari pengajuan"
                />
              </div>
              <button
                class="btn btn-primary"
                type="button"
                @click="openAddModal"
              >
                <Icons name="plus" :size="16" />
                Ajukan Baru
              </button>
            </template>
          </DataCardHead>

          <div v-if="loading" class="loading-state">
            <div class="loading-spinner"></div>
            <p>Memuat data...</p>
          </div>

          <div v-else-if="apps.length === 0 && hasActiveSearch" class="global-empty">
            <p class="global-empty-title">Tidak ada hasil pencarian</p>
            <p class="global-empty-text">
              Tidak ada pengajuan atau UAT yang cocok dengan kata kunci ini. Coba istilah lain atau kosongkan pencarian.
            </p>
            <button type="button" class="btn btn-ghost" @click="clearSearch">
              Hapus pencarian
            </button>
          </div>

          <div v-else-if="apps.length === 0" class="global-empty">
            <div class="global-empty-icon-wrapper">
              <Icons name="folder-plus" :size="48" class="global-empty-icon" />
            </div>
            <h3 class="global-empty-title">Belum Ada Pengajuan atau UAT</h3>
            <p class="global-empty-text">
              Belum ada pengajuan aplikasi atau aplikasi yang perlu diuji UAT.
              Mulai langkah pertama dengan mendaftarkan aplikasi Unit Kerja Anda.
            </p>
            <button class="btn btn-primary" @click="openAddModal">
              <Icons name="plus" :size="16" />
              Buat Pengajuan Baru
            </button>
          </div>

          <template v-else>
            <DataTable>
                <thead>
                  <tr>
                    <th scope="col" class="col-num">#</th>
                    <th scope="col">Nama Aplikasi</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tanggal Pengajuan</th>
                    <th scope="col" class="col-aksi">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(app, idx) in apps" :key="app.id" @click="viewDetail(app.id)" class="data-table-row is-clickable">
                    <td class="col-num">{{ ((appsPagination.currentPage - 1) * appsPagination.perPage) + idx + 1 }}</td>
                    <td>
                      <div class="app-name-cell">
                        <span class="app-name-main">{{ app.nama_aplikasi }}</span>
                        <span class="app-name-sub">{{ app.nama_layanan }}</span>
                      </div>
                    </td>
                    <td>
                      <div class="status-cell">
                        <span :class="['badge', getStatusBadgeClass(app.status)]" :title="getStatusTooltip(app.status)">
                          {{ getStatusLabel(app.status) }}
                        </span>
                        <div v-if="getNextActionText(app)" :class="['next-action-text', getNextActionClass(app)]">
                          {{ getNextActionText(app) }}
                        </div>
                      </div>
                    </td>
                    <td class="col-date">{{ app.created_at ? new Date(app.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-' }}</td>
                    <td @click.stop>
                      <div class="action-group">
                        <button
                          class="action-btn table-action-btn view-btn"
                          type="button"
                          title="Lihat progres"
                          @click.stop="viewDetail(app.id)"
                        >
                          <Icons name="eye" :size="14" />
                          Lihat Progres
                        </button>
                        <button
                          v-if="app.status === 'diajukan'"
                          class="action-btn table-action-btn withdraw-btn"
                          type="button"
                          title="Tarik pengajuan"
                          @click.stop="askWithdraw(app)"
                        >
                          <Icons name="trash" :size="14" />
                          Tarik
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
            </DataTable>

            <div v-if="appsPagination.lastPage > 1" class="pagination">
              <div class="pagination-info">
                Menampilkan {{ ((appsPagination.currentPage - 1) * appsPagination.perPage) + 1 }} -
                {{ Math.min(appsPagination.currentPage * appsPagination.perPage, appsPagination.total) }}
                dari {{ appsPagination.total }} item
              </div>
              <div class="pagination-controls">
                <button
                  type="button"
                  @click="changePage(appsPagination.currentPage - 1)"
                  :disabled="appsPagination.currentPage === 1"
                  class="pagination-btn"
                >
                  <Icons name="chevron-left" :size="16" />
                </button>

                <button
                  v-for="page in appPageNumbers"
                  :key="page"
                  type="button"
                  @click="page !== '...' && changePage(page)"
                  :class="['pagination-btn', { active: page === appsPagination.currentPage, disabled: page === '...' }]"
                >
                  {{ page }}
                </button>

                <button
                  type="button"
                  @click="changePage(appsPagination.currentPage + 1)"
                  :disabled="appsPagination.currentPage === appsPagination.lastPage"
                  class="pagination-btn"
                >
                  <Icons name="chevron-right" :size="16" />
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <AplikasiFormModal :show="showAppModal" :app="null" @close="closeAppModal" @saved="onAppSaved" />

    <!-- Modal konfirmasi tarik pengajuan -->
    <div v-if="confirmWithdrawApp" class="modal active" @click.self="cancelWithdraw">
      <div class="modal-content withdraw-confirm-modal">
        <div class="modal-header">
          <h3>Tarik Pengajuan</h3>
          <button class="close-btn" @click="cancelWithdraw">&times;</button>
        </div>
        <div class="withdraw-confirm-body">
          <p class="withdraw-confirm-text">
            Pengajuan <strong>{{ confirmWithdrawApp.nama_aplikasi }}</strong> akan ditarik dan tidak lagi tampil sebagai pengajuan aktif.
          </p>
          <p class="withdraw-confirm-note">Lanjutkan hanya jika Anda memang ingin membatalkan pengajuan ini.</p>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn btn-cancel" :disabled="withdrawing" @click="cancelWithdraw">
            Batal
          </button>
          <button type="button" class="btn btn-danger" :disabled="withdrawing" @click="doWithdraw">
            <span v-if="withdrawing">Menarik...</span>
            <span v-else>Tarik Pengajuan</span>
          </button>
        </div>
      </div>
    </div>
  </UserLayout>
</template>

<style scoped>
/* ===== WELCOME CARD ===== */
.uk-welcome-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  flex-wrap: wrap;
  background: linear-gradient(135deg, #1e3a8a 0%, #2c4fa8 100%);
  border-radius: 14px;
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
  font-size: 12px;
}

.uk-bc-link {
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

.uk-bc-link:hover {
  color: rgba(255, 255, 255, 0.9);
}

.uk-bc-sep {
  color: rgba(255, 255, 255, 0.35);
}

.uk-bc-current {
  color: rgba(255, 255, 255, 0.8);
  font-weight: 500;
}

.uk-welcome-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  color: #fff;
}

.uk-welcome-sub {
  margin: 0;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.75);
  line-height: 1.5;
}

.uk-welcome-btn {
  flex-shrink: 0;
  padding: 10px 20px;
  font-size: 14px;
  border-radius: 8px;
  background: #fff;
  color: #1e3a8a;
  font-weight: 600;
  border: none;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
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
  border-radius: 12px;
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
  font-size: 14px;
  color: #4b5563;
  font-weight: 600;
  letter-spacing: 0.02em;
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

.bg-blue { background: #eff6ff; color: #3b82f6; }
.bg-green { background: #ecfdf5; color: #10b981; }
.bg-amber { background: #fffbeb; color: #f59e0b; }
.bg-red { background: #fef2f2; color: #ef4444; }

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #111827;
  line-height: 1;
  letter-spacing: -0.02em;
}

.col-date {
  white-space: nowrap;
  font-size: 13px;
  color: var(--notion-text-secondary);
}

.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  font-weight: 600;
  font-size: 14px;
  color: var(--notion-text);
}

.app-name-sub {
  font-size: 12px;
  color: var(--notion-text-secondary);
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
  border-radius: 16px;
  margin: 20px;
}

.global-empty-icon-wrapper {
  width: 64px;
  height: 64px;
  margin: 0 auto 20px;
  background: #f3f4f6;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
}

.global-empty-title {
  font-size: 18px;
  font-weight: 700;
  color: #111827;
  margin-bottom: 8px;
}

.global-empty-text {
  font-size: 14px;
  color: #6b7280;
  max-width: 320px;
  margin: 0 auto 24px;
}

/* ===== WITHDRAW ===== */
.action-group {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.withdraw-btn {
  background: rgba(220, 38, 38, 0.07) !important;
  color: #dc2626 !important;
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
  font-size: 14px;
  color: var(--notion-text);
  line-height: 1.6;
  margin: 0 0 8px;
}

.withdraw-confirm-note {
  font-size: 13px;
  color: var(--notion-text-secondary);
  margin: 0;
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
    font-size: 28px;
  }

  .stat-label {
    font-size: 13px;
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

  .uk-card-head .search-group {
    max-width: 100%;
    width: 100%;
  }
}

@media (max-width: 480px) {
  .uk-welcome-title {
    font-size: 18px;
  }
}

.next-action-text {
  font-size: 11px;
  color: #d97706;
  font-weight: 500;
  line-height: 1.2;
}

.next-action-text.is-waiting {
  color: #2563eb;
}
</style>
