<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import UserLayout from '../layouts/UserLayout.vue'
import DataCardHead from '../components/DataCardHead.vue'
import DataTable from '../components/DataTable.vue'
import Icons from '../components/Icons.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { getHomeByRole } from '../constants/roles'
import { usePagination } from '../composables/usePagination.js'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel, getStatusBadgeClass } from '../constants/status'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const apps = ref([])
const loading = ref(false)
const searchAplikasi = ref('')
let filterTimer = null
const basePath = computed(() => getHomeByRole(auth.role).path)
const pageTitle = computed(() => auth.role === 'tim_uji_keamanan' ? 'Uji Keamanan Aplikasi' : 'Kelola Aplikasi')
const listTitle = computed(() => auth.role === 'tim_uji_keamanan' ? 'Daftar Aplikasi Tahap Testing' : 'Daftar Aplikasi')
const hasActiveSearch = computed(() => !!searchAplikasi.value?.trim())

// Pagination state
const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0
})

const { pageNumbers: appPageNumbers } = usePagination(appsPagination)

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
    const statusByRole = {
      tim_implementasi_aplikasi: 'analisa_desain,pengembangan,perbaikan_uat,perbaikan_keamanan',
      devops_developer: 'siap_deploy,deployed_staging,deployed_production',
      tim_uji_keamanan: 'uji_keamanan',
    }
    const status = statusByRole[auth.role] || 'analisa_desain'
    const statusFilter = `status=${encodeURIComponent(status)}&`
    const response = await http.get(`/aplikasi?${searchQuery}${statusFilter}per_page=${appsPagination.value.perPage}&page=${page}`)
    
    // Handle Laravel pagination format
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
    }
  } catch (error) {
    warnDev('[UserDashboard] Gagal memuat daftar aplikasi', error)
    toast.push('Tidak dapat memuat daftar aplikasi.', 'error', 4000)
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

// Pagination helpers
function changePage(page) {
  loadAplikasiData(page)
}

function clearSearch() {
  searchAplikasi.value = ''
  loadAplikasiData(1)
}

function rowNumber(idx) {
  return ((appsPagination.value.currentPage - 1) * appsPagination.value.perPage) + idx + 1
}

function viewDetail(appId) {
  const rolePath = getHomeByRole(auth.role).path
  const targetPath = `${rolePath}/app/${appId}`

  router.push(targetPath).catch((error) => {
    warnDev('[UserDashboard] Navigasi path gagal', error)
    const routeName = `${rolePath.replace('/', '')}-app-detail`
    router.push({ name: routeName, params: { id: appId } }).catch((error_) => {
      warnDev('[UserDashboard] Navigasi nama route gagal', error_)
      toast.push('Tidak dapat membuka detail aplikasi.', 'error', 4000)
    })
  })
}

</script>

<template>
  <UserLayout>
    <div class="container workspace-dashboard">
      <div class="workspace-hero-card">
        <div class="workspace-hero-text">
          <nav class="workspace-hero-breadcrumb" aria-label="breadcrumb">
            <button @click="router.push(basePath)" class="ah-bc-link">
              <Icons name="dashboard" :size="12" />
              Dashboard
            </button>
            <span class="ah-bc-sep">/</span>
            <span class="ah-bc-current">{{ pageTitle }}</span>
          </nav>
          <h2 class="workspace-hero-title">{{ pageTitle }}</h2>
          <p class="workspace-hero-sub">{{ listTitle }}</p>
        </div>
      </div>


    <!-- Aplikasi Section -->
    <div class="content-section active">
      <div class="card">
        <DataCardHead :title="listTitle">
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
                aria-label="Cari aplikasi" 
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
            Tidak ada aplikasi yang cocok dengan kata kunci pencarian ini.
          </p>
          <button type="button" class="btn btn-secondary" @click="clearSearch">
            Hapus pencarian
          </button>
        </div>
        <div v-else-if="apps.length === 0" class="global-empty">
          <div class="global-empty-icon-wrapper">
            <Icons name="inbox" :size="48" class="global-empty-icon" />
          </div>
          <h3 class="global-empty-title">Belum Ada Aplikasi</h3>
          <p class="global-empty-text">
            Belum ada aplikasi yang membutuhkan tindakan pada tahap ini.
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
                  <span class="app-name-main">{{ app.nama_aplikasi || app.nama_layanan || '-' }}</span>
                  <span class="app-name-sub">{{ app.nama_layanan || '-' }}</span>
                </div>
              </td>
              <td>{{ app.kode_unitOrganisasi }}</td>
              <td>
                <span :class="['badge', getStatusBadgeClass(app.status)]">
                  {{ getShortStatusLabel(app.status) }}
                </span>
              </td>
              <td @click.stop>
                <div class="action-group">
                  <button class="action-btn table-action-btn view-btn" @click="viewDetail(app.id)">
                    <Icons name="eye" :size="14" />
                    Detail
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
    </div>
  </UserLayout>
</template>

<style scoped>
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

@media (max-width: 768px) {
  .workspace-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
  }
}
</style>
