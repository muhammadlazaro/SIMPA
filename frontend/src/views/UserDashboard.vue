<script setup>
import { Button } from '@idds/vue'
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import AsyncState from '../components/AsyncState.vue'
import DataTable from '../components/DataTable.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import { getHomeByRole } from '../constants/roles'
import { warnDev } from '../utils/logger'
import { getShortStatusLabel, getStatusBadgeClass } from '../constants/status'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const apps = ref([])
const loading = ref(false)
const loadError = ref('')
const searchAplikasi = ref('')
let filterTimer = null
const basePath = computed(() => getHomeByRole(auth.role).path)
const workspaceCopy = computed(() => {
  const copy = {
    tim_implementasi_aplikasi: {
      eyebrow: 'Tim Implementasi',
      title: 'Implementasi aplikasi',
      description: 'Kelola aplikasi yang siap dikembangkan dan tindak lanjuti hasil evaluasi.',
      list: 'Aplikasi yang perlu diimplementasikan',
      empty: 'Belum ada aplikasi yang membutuhkan implementasi.',
    },
    devops_developer: {
      eyebrow: 'DevOps',
      title: 'Deployment aplikasi',
      description: 'Pantau aplikasi yang siap dipublikasikan ke staging maupun production.',
      list: 'Aplikasi yang siap dideploy',
      empty: 'Belum ada aplikasi yang siap masuk proses deployment.',
    },
    tim_uji_keamanan: {
      eyebrow: 'Tim Uji Keamanan',
      title: 'Pengujian keamanan',
      description: 'Tinjau aplikasi pada tahap pengujian dan dokumentasikan hasil pemeriksaan.',
      list: 'Aplikasi yang perlu diuji',
      empty: 'Belum ada aplikasi yang membutuhkan pengujian keamanan.',
    },
  }

  return copy[auth.role] || {
    eyebrow: 'Aplikasi',
    title: 'Daftar aplikasi',
    description: 'Pantau aplikasi yang membutuhkan tindak lanjut Anda.',
    list: 'Aplikasi',
    empty: 'Belum ada aplikasi yang membutuhkan tindakan.',
  }
})
const hasActiveSearch = computed(() => !!searchAplikasi.value?.trim())

// Pagination state
const appsPagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 30,
  total: 0
})

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
        perPage: Number(meta.per_page) || appsPagination.value.perPage || 30,
        total: Number(meta.total) || 0
      }
    } else {
      apps.value = response.data || []
    }
  } catch (error) {
    warnDev('[UserDashboard] Gagal memuat daftar aplikasi', error)
    loadError.value = 'Daftar aplikasi belum dapat dimuat. Periksa koneksi lalu coba lagi.'
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
      :eyebrow="workspaceCopy.eyebrow"
      :title="workspaceCopy.title"
      :description="workspaceCopy.description"
    />

    <div class="ui-page-content">
      <section class="ui-panel" aria-labelledby="workspace-list-title">
        <header class="ui-panel-header">
          <div>
            <h2 id="workspace-list-title">{{ workspaceCopy.list }}</h2>
            <p class="ui-table-subtitle">{{ appsPagination.total }} aplikasi memerlukan tindak lanjut</p>
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
          :empty-title="hasActiveSearch ? 'Aplikasi tidak ditemukan' : 'Belum ada pekerjaan'"
          :empty-description="hasActiveSearch
            ? 'Coba kata kunci lain atau hapus pencarian.'
            : workspaceCopy.empty"
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
                <th scope="col">Kode unit</th>
                <th scope="col">Status</th>
                <th scope="col" class="ui-table-actions"><span class="sr-only">Aksi</span></th>
              </tr>
            </template>
            <template #body>
              <tr v-for="(app, idx) in apps" :key="app.id">
                <td data-label="Nomor" data-hide-mobile="true" class="col-num">{{ rowNumber(idx) }}</td>
                <td data-primary="true">
                  <RouterLink class="ui-table-link" :to="`${basePath}/app/${app.id}`">
                    {{ app.nama_aplikasi || app.nama_layanan || '-' }}
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
                  <IconActionCell :label="`Aksi untuk ${app.nama_aplikasi || app.nama_layanan}`">
                    <IconActionButton
                      label="Lihat detail"
                      icon="eye"
                      @click="router.push(`${basePath}/app/${app.id}`)"
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
  </div>
</template>
