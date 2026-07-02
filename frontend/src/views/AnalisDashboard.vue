<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePagination } from '../composables/usePagination.js'
import { useRouter } from 'vue-router'
import { useToastStore } from '../stores/toast'
import http from '../lib/http'
import AnalisLayout from '../layouts/AnalisLayout.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import AnalisaDesainModal from '../components/AnalisaDesainModal.vue'
import Icons from '../components/Icons.vue'
import { warnDev } from '../utils/logger'
import { getStatusBadgeClass as _getStatusBadgeClass } from '../constants/status'

const toast = useToastStore()
const router = useRouter()


const apps = ref([])
const loading = ref(false)
const searchAplikasi = ref('')
const hasActiveSearch = computed(() => !!searchAplikasi.value?.trim())
let filterTimer = null
// Ringkasan analisa per aplikasi untuk kolom-kolom tabel
const analisaMap = ref({})

// Pagination state
const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0
})

const { pageNumbers: appPageNumbers } = usePagination(appsPagination)

const showAnalisaModal = ref(false)
const analisaAppName = ref('')
const analisaAppId = ref(null)
const analisaApp = ref(null)
const analisaReadOnly = ref(false)
const modalFocusSection = ref(null)
const modalMode = ref('full')
const hideTransaksi = ref(false)

const uiPlatformLabelMap = {
  dws: 'DWS',
  layanan: 'Layanan',
}

function formatUiPlatformLabel(value) {
  if (!value) return ''
  return uiPlatformLabelMap[value] || value
}

onMounted(async () => {
  await loadAplikasiData()
})

onBeforeUnmount(() => {
  if (filterTimer) clearTimeout(filterTimer)
})

async function loadAplikasiData(page = 1) {
  loading.value = true
  try {
    const searchQuery = searchAplikasi.value?.trim() ? `q=${encodeURIComponent(searchAplikasi.value.trim())}&` : ''
    const statusFilter = `status=${encodeURIComponent('layak,analisa_desain')}&`
    const response = await http.get(`/aplikasi?${searchQuery}${statusFilter}per_page=${appsPagination.value.perPage}&page=${page}`)
    
    if (response.data.data) {
      const meta = response.data.meta || response.data
      apps.value = response.data.data
      appsPagination.value = {
        currentPage: Number(meta.current_page) || page,
        lastPage: Number(meta.last_page) || 1,
        perPage: Number(meta.per_page) || appsPagination.value.perPage || 10,
        total: Number(meta.total) || 0
      }
    } else {
      apps.value = response.data || []
      appsPagination.value.currentPage = page
    }
    await loadAnalisaSummaryForPage()
  } catch (error) {
    warnDev('[AnalisDashboard] loadAplikasiData error:', error)
    toast.push('Gagal memuat data aplikasi', 'error')
  } finally {
    loading.value = false
  }
}

function scheduleFilterUpdate() {
  if (filterTimer) clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    loadAplikasiData(1)
  }, 350)
}

function clearSearch() {
  searchAplikasi.value = ''
  loadAplikasiData(1)
}

async function loadAnalisaSummaryForPage() {
  const ids = apps.value.map((a) => a.id)
  if (ids.length === 0) return
  const empty = { ui: [], interop: [], storage: [], aktor: [], transaksiCount: 0 }
  try {
    const resp = await http.get('/analisa-desain/summary', {
      params: { aplikasi_ids: ids },
    })
    const map = resp.data?.data || {}
    const next = { ...analisaMap.value }
    for (const id of ids) {
      next[id] = map[String(id)] || empty
    }
    analisaMap.value = next
  } catch (e) {
    warnDev('[AnalisDashboard] loadAnalisaSummary error:', e)
    const next = { ...analisaMap.value }
    for (const id of ids) {
      next[id] = empty
    }
    analisaMap.value = next
  }
}

function viewDetail(appId) {
  router.push(`/analis-desain/app/${appId}`)
}

/** Indikator 5 aspek ringkas (selaras kolom tabel) untuk progress analisa */
function analisaProgress(appId) {
  const m = analisaMap.value[appId] || {}
  const labels = ['UI', 'Interop', 'Storage', 'Aktor', 'Transaksi']
  const ok = [
    (m.ui || []).length > 0,
    (m.interop || []).length > 0,
    (m.storage || []).length > 0,
    (m.aktor || []).length > 0,
    (m.transaksiCount || 0) > 0,
  ]
  const done = ok.filter(Boolean).length
  const title = labels.map((l, i) => `${l}: ${ok[i] ? 'sudah' : 'belum'}`).join(' - ')
  return {
    done,
    total: 5,
    title,
    badgeClass:
      done === 5 ? 'badge-success' : done === 0 ? 'badge-secondary' : 'badge-warning',
    label: done === 5 ? 'Lengkap' : `${done}/5`,
  }
}

async function openAnalisaEdit(app) {
  try {
    // Fetch fresh data
    const resp = await http.get(`/aplikasi/${app.id}`)
    const fresh = resp.data.data || resp.data || app
    
    analisaAppId.value = fresh.id
    analisaAppName.value = fresh.nama_aplikasi
    analisaApp.value = fresh
    analisaReadOnly.value = false
    modalFocusSection.value = null
    modalMode.value = 'full'
    hideTransaksi.value = false
    showAnalisaModal.value = true
  } catch (e) {
    toast.push('Gagal memuat data aplikasi', 'error')
  }
}


// Pagination helpers
function changePage(page) {
  loadAplikasiData(page)
}

function rowNumber(idx) {
  const start = ((appsPagination.value.currentPage - 1) * appsPagination.value.perPage) + 1
  return start + idx
}

// Analis (Analis Desain) melihat semua status sebagai secondary kecuali yang relevan
const ANALIS_BADGE_OVERRIDE = {
  'diajukan': 'badge-secondary',
  'terverifikasi': 'badge-secondary',
}

function getStatusBadgeClass(status) {
  return ANALIS_BADGE_OVERRIDE[status] ?? _getStatusBadgeClass(status, 'badge-secondary')
}

function onAnalisaSaved() {
  loadAplikasiData(appsPagination.value.currentPage)
}
</script>

<template>
  <AnalisLayout>
    <div class="container workspace-dashboard">
      <div class="workspace-hero-card">
        <div class="workspace-hero-text">
          <nav class="workspace-hero-breadcrumb" aria-label="breadcrumb">
            <button @click="router.push('/analis-desain')" class="ah-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="ah-bc-sep">/</span>
            <span class="ah-bc-current">Analisa &amp; Desain</span>
          </nav>
          <h2 class="workspace-hero-title">Analisa &amp; Desain</h2>
          <p class="workspace-hero-sub">Pantau dan lengkapi hasil analisa desain aplikasi.</p>
        </div>
      </div>

    <!-- Aplikasi Section -->
    <div class="content-section active">
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
                @input="scheduleFilterUpdate" 
                placeholder="Cari aplikasi..."
                maxlength="50" 
                aria-label="Cari aplikasi..."
              />
            </div>
          </template>
        </DataCardHead>
        
        <div v-if="loading" class="loading-state">
          <div class="loading-spinner"></div>
          <p>Memuat data aplikasi...</p>
        </div>
        <div v-else-if="apps.length === 0 && hasActiveSearch" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="search" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Tidak Ada Hasil</h3>
          <p class="global-empty-text">
            Tidak ada aplikasi tahap analisa yang cocok dengan kata kunci ini.
          </p>
          <button type="button" class="btn btn-secondary" @click="clearSearch">
            Hapus pencarian
          </button>
        </div>
        <div v-else-if="apps.length === 0" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="inbox" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Tidak Ada Aplikasi</h3>
          <p class="global-empty-text">
            Tidak ada aplikasi di tahap Analisa dan Desain saat ini.
          </p>
          <button class="btn btn-secondary" @click="loadAplikasiData(1)">
            <Icons name="refresh-cw" :size="16" />
            Muat ulang
          </button>
        </div>
        <div v-else>
        <DataTable>
          <thead>
            <tr>
              <th scope="col" class="col-num">#</th>
              <th scope="col">Nama Aplikasi</th>
              <th scope="col">UI Platform</th>
              <th scope="col">Interoperabilitas</th>
              <th scope="col">Storage</th>
              <th scope="col">Aktor</th>
              <th scope="col">Transaksi</th>
              <th scope="col" class="col-aksi">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(app, idx) in apps" :key="app.id" class="data-table-row is-clickable" @click="viewDetail(app.id)">
              <td class="col-num">{{ rowNumber(idx) }}</td>
              <td>
                <div class="app-name-cell">
                  <span class="app-name-main">{{ app.nama_aplikasi || '-' }}</span>
                  <span class="app-name-sub">{{ app.nama_layanan || '-' }}</span>
                </div>
              </td>
              
              <td>
                <div class="table-badges">
                  <span v-for="p in (analisaMap[app.id]?.ui || [])" :key="'ui-'+p" class="badge badge-info">{{ formatUiPlatformLabel(p) }}</span>
                  <span v-if="!(analisaMap[app.id]?.ui || []).length" class="table-empty-pill">Belum ada</span>
                </div>
              </td>
              <td>
                <div class="table-badges">
                  <span v-for="v in (analisaMap[app.id]?.interop || [])" :key="'io-'+v" class="badge badge-success">{{ v }}</span>
                  <span v-if="!(analisaMap[app.id]?.interop || []).length" class="table-empty-pill">Belum ada</span>
                </div>
              </td>
              <td>
                <div class="table-badges">
                  <span v-for="s in (analisaMap[app.id]?.storage || [])" :key="'st-'+s" class="badge badge-warning">{{ s==='db'?'Database': s==='object-storage'?'Object Storage': s }}</span>
                  <span v-if="!(analisaMap[app.id]?.storage || []).length" class="table-empty-pill">Belum ada</span>
                </div>
              </td>
              <td>
                <div class="table-badges">
                  <span v-for="ak in (analisaMap[app.id]?.aktor || [])" :key="'ak-'+ak" class="badge badge-info">{{ ak }}</span>
                  <span v-if="!(analisaMap[app.id]?.aktor || []).length" class="table-empty-pill">Belum ada</span>
                </div>
              </td>
              <td>
                <div class="table-badges">
                  <span class="badge">{{ analisaMap[app.id]?.transaksiCount || 0 }} endpoint</span>
                </div>
              </td>
              <td class="action-cell" @click.stop>
                <div class="action-group">
                  <button
                    type="button"
                    class="action-btn table-action-btn view-btn"
                    title="Buka halaman lengkap: tab proyek, ringkasan aplikasi, dan konfigurasi turunan"
                    @click.stop="viewDetail(app.id)"
                  >
                    <Icons name="eye" :size="14" />
                    Detail
                  </button>
                  <button
                    type="button"
                    class="action-btn table-action-btn edit-btn"
                    title="Buka formulir analisa di jendela untuk mengubah UI, interop, penyimpanan, aktor, dan transaksi"
                    @click.stop="openAnalisaEdit(app)"
                  >
                    <Icons name="edit" :size="14" />
                    Edit
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

    <AnalisaDesainModal
      :show="showAnalisaModal"
      :app-name="analisaAppName"
      :aplikasi-id="analisaAppId"
      :aplikasi="analisaApp"
      :read-only="analisaReadOnly"
      :focus-section="modalFocusSection"
      :mode="modalMode"
      :hide-transaksi="hideTransaksi"
      @close="showAnalisaModal = false"
      @saved="onAnalisaSaved"
    />
    </div>
  </AnalisLayout>
</template>

<style scoped>
.table-badges { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

.table-empty-pill {
  display: inline-flex;
  align-items: center;
  min-height: 24px;
  padding: 4px 8px;
  border-radius: 999px;
  background: var(--notion-muted-surface);
  color: var(--notion-text-secondary);
  border: 1px dashed var(--notion-border);
  font-size: 12px;
  font-weight: 600;
}

.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  color: var(--notion-text);
  font-size: 14px;
  font-weight: 700;
}

.app-name-sub {
  color: var(--notion-text-secondary);
  font-size: 12px;
}

.action-cell {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 6px;
}
.progress-badge { cursor: help; font-weight: 600; }

.action-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
}

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

@media (max-width: 768px) {
  .workspace-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
  }
}
</style>

