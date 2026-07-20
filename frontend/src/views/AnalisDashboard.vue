<script setup>
import { Button } from '@idds/vue'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useToastStore } from '../stores/toast'
import http from '../lib/http'
import DataTable from '../components/DataTable.vue'
import AnalisaDesainModal from '../components/AnalisaDesainModal.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import AsyncState from '../components/AsyncState.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel, getStatusBadgeClass as _getStatusBadgeClass } from '../constants/status'

const toast = useToastStore()
const router = useRouter()


const apps = ref([])
const loading = ref(false)
const loadError = ref('')
const searchAplikasi = ref('')
const hasActiveSearch = computed(() => !!searchAplikasi.value?.trim())
let filterTimer = null

// Pagination state
const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 30,
  total: 0
})

const showAnalisaModal = ref(false)
const analisaAppName = ref('')
const analisaAppId = ref(null)
const analisaApp = ref(null)
const analisaReadOnly = ref(false)
const modalFocusSection = ref(null)
const modalMode = ref('full')
const hideTransaksi = ref(false)

onMounted(async () => {
  await loadAplikasiData()
})

onBeforeUnmount(() => {
  if (filterTimer) clearTimeout(filterTimer)
})

async function loadAplikasiData(page = 1) {
  loading.value = true
  loadError.value = ''
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
        perPage: Number(meta.per_page) || appsPagination.value.perPage || 30,
        total: Number(meta.total) || 0
      }
    } else {
      apps.value = response.data || []
      appsPagination.value.currentPage = page
    }
  } catch (error) {
    warnDev('[AnalisDashboard] loadAplikasiData error:', error)
    loadError.value = error.response?.data?.message || 'Antrian analisis belum dapat dimuat.'
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

function viewDetail(appId) {
  router.push(`/analis-desain/app/${appId}`)
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

function statusToneClass(status) {
  const badgeClass = getStatusBadgeClass(status)
  if (badgeClass.includes('success')) return 'success'
  if (badgeClass.includes('danger')) return 'danger'
  if (badgeClass.includes('warning')) return 'warning'
  return ''
}

function onAnalisaSaved() {
  loadAplikasiData(appsPagination.value.currentPage)
}
</script>

<template>
  <div class="ui-page">
    <PageHeader
      eyebrow="Analis Desain"
      title="Antrian analisis"
      description="Tinjau aplikasi yang telah dinyatakan layak dan lengkapi rancangan teknisnya."
    />

    <div class="ui-page-content">
      <section class="ui-panel" aria-labelledby="analysis-list-title">
        <header class="ui-panel-header">
          <div>
            <h2 id="analysis-list-title">Aplikasi siap dianalisis</h2>
            <p class="ui-table-subtitle">{{ appsPagination.total }} aplikasi dalam antrian</p>
          </div>
          <div class="ui-panel-actions">
            <SearchField
              v-model="searchAplikasi"
              label="Cari aplikasi"
              placeholder="Cari aplikasi"
              @update:model-value="scheduleFilterUpdate"
            />
          </div>
        </header>
        
        <AsyncState
          :loading="loading"
          :error="loadError"
          :empty="apps.length === 0"
          :empty-icon="hasActiveSearch ? 'search' : 'inbox'"
          :empty-title="hasActiveSearch ? 'Aplikasi tidak ditemukan' : 'Antrian analisis kosong'"
          :empty-description="hasActiveSearch
            ? 'Coba kata kunci lain atau hapus pencarian.'
            : 'Belum ada aplikasi yang siap masuk tahap analisis desain.'"
          @retry="loadAplikasiData(appsPagination.currentPage)"
        >
          <template v-if="hasActiveSearch" #action>
            <Button hierarchy="secondary" size="sm" @click="clearSearch">
              Hapus pencarian
            </Button>
          </template>

          <DataTable>
            <thead>
              <tr>
                <th scope="col" class="col-num">#</th>
                <th scope="col">Nama aplikasi</th>
                <th scope="col">Kode unit</th>
                <th scope="col">Status</th>
                <th scope="col" class="ui-table-actions"><span class="sr-only">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(app, idx) in apps" :key="app.id">
                <td data-label="Nomor" data-hide-mobile="true" class="col-num">{{ rowNumber(idx) }}</td>
                <td data-primary="true">
                  <RouterLink
                    class="ui-table-link"
                    :to="{ name: 'analis-desain-app-detail', params: { id: app.id } }"
                  >
                    {{ app.nama_aplikasi || '-' }}
                  </RouterLink>
                  <span class="ui-table-subtitle">{{ app.nama_layanan || '-' }}</span>
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
                    <IconActionButton label="Edit analisis" icon="edit" @click="openAnalisaEdit(app)" />
                  </IconActionCell>
                </td>
              </tr>
            </tbody>
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
</template>

<style scoped>
.app-name-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.app-name-main {
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-caption-line);
}

.app-name-sub {
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.action-cell {
  white-space: nowrap;
}

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

@media (max-width: 768px) {
  .workspace-hero-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 16px;
  }
}
</style>

