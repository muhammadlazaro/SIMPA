<script setup>
import {
  Button,
  Modal,
  SingleFileUpload,
  Stepper,
} from '@idds/vue'
import {
  IconArrowLeft,
  IconArrowRight,
  IconCheck,
  IconPlus,
} from '@tabler/icons-vue'
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import http from '../lib/http'
import ConfirmationDrawer from '../components/ConfirmationDrawer.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import AsyncState from '../components/AsyncState.vue'
import DataTable from '../components/DataTable.vue'
import IddsSelect from '../components/IddsSelect.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useToastStore } from '../stores/toast'
import { resolveIddsFileSelection } from '../utils/fileUpload'
import { warnDev } from '../utils/logger'
import { getRfcStatusBadgeClass } from '../constants/status'

const router = useRouter()
const toast = useToastStore()


const rfcs = ref([])
const loading = ref(false)
const loadError = ref('')
const search = ref('')
const hasActiveSearch = computed(() => !!search.value?.trim())
const pagination = ref({ currentPage: 1, lastPage: 1, perPage: 30, total: 0 })

const appsActive = ref([])
const showForm = ref(false)
const savingRfc = ref(false)
const editing = ref(null)
const rfcStep = ref(1)
const selectedApp = ref('')
const rfcFile = ref(null)
const selectedTipe = ref('')
const selectedPelaksana = ref('')
const selectedStatus = ref('')
const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deletingRfc = ref(false)
const stats = ref({ total: 0, diajukan: 0, diproses: 0, production: 0 })
const applicationOptions = computed(() => appsActive.value.map((app) => ({
  label: app.name,
  value: app.id,
})))
const rfcTypeOptions = [
  { label: 'Medium', value: 'Medium' },
  { label: 'Standar', value: 'Standar' },
  { label: 'Minor', value: 'Minor' },
  { label: 'Major', value: 'Major' },
  { label: 'Darurat', value: 'Darurat' },
]
const executorOptions = [
  { label: 'Internal Pusdatik', value: 'Internal Pusdatik' },
  { label: 'Eksternal', value: 'Eksternal' },
  { label: 'Internal D13', value: 'Internal D13' },
]
const statusOptions = [
  { label: 'Diajukan', value: 'Diajukan' },
  { label: 'Analisis Desain', value: 'Analisa Desain' },
  { label: 'Dev-Staging', value: 'Dev-Staging' },
  { label: 'UAT', value: 'UAT' },
  { label: 'Production', value: 'Production' },
]
const rfcSteps = [
  { label: 'Data RFC' },
  { label: 'Formulir RFC' },
]

let searchTimer = null

function resetForm() {
  rfcStep.value = 1
  selectedApp.value = ''
  selectedTipe.value = ''
  selectedPelaksana.value = ''
  selectedStatus.value = ''
  rfcFile.value = null
}

async function openAdd() {
  editing.value = null
  resetForm()
  showForm.value = true
  await loadActiveApps()
}

function openEdit(item) {
  editing.value = item
  rfcStep.value = 1
  showForm.value = true
}

function closeForm() {
  if (savingRfc.value) return
  showForm.value = false
}

function openDetail(item) {
  const appId = item?.aplikasi_id || item?.aplikasi?.id
  if (!appId) {
    toast.push('Aplikasi RFC tidak ditemukan.', 'error')
    return
  }

  router.push({ name: 'pengelola-aplikasi-app-detail', params: { id: appId } })
}

function confirmDelete(item) {
  deleteTarget.value = item
  showDeleteModal.value = true
}

function closeDeleteModal() {
  if (deletingRfc.value) return
  showDeleteModal.value = false
  deleteTarget.value = null
}

async function deleteRfc() {
  if (!deleteTarget.value) return
  deletingRfc.value = true
  try {
    await http.delete(`/rfc/${deleteTarget.value.id}`)
    toast.push('RFC berhasil dihapus', 'success')
    showDeleteModal.value = false
    deleteTarget.value = null
    await Promise.all([loadRfcs(pagination.value.currentPage), loadRfcStats()])
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal menghapus RFC'
    toast.push(message, 'error')
    warnDev('[PengelolaRfc] deleteRfc error:', error)
  } finally {
    deletingRfc.value = false
  }
}

function onRfcFileChange(file, validation) {
  const selection = resolveIddsFileSelection(file, validation, 'File tidak sesuai ketentuan.')
  rfcFile.value = selection.file

  if (selection.error) {
    toast.push(selection.error, 'error')
  }
}

function removeRfcFile() {
  rfcFile.value = null
}

function validateRfcData() {
  if (!selectedApp.value || !selectedTipe.value || !selectedPelaksana.value || !selectedStatus.value) {
    toast.push('Mohon lengkapi semua field yang wajib diisi', 'error')
    return false
  }

  return true
}

function nextRfcStep() {
  if (!validateRfcData()) return
  rfcStep.value = 2
}

function previousRfcStep() {
  rfcStep.value = 1
}

function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    loadRfcs(1)
  }, 350)
}

function clearSearch() {
  search.value = ''
  loadRfcs(1)
}

async function loadRfcStats() {
  try {
    const resp = await http.get('/rfc/stats')
    const data = resp.data?.data || {}
    stats.value = {
      total: Number(data.total) || 0,
      diajukan: Number(data.diajukan) || 0,
      diproses: Number(data.diproses) || 0,
      production: Number(data.production) || 0,
    }
  } catch (error) {
    warnDev('[PengelolaRfc] loadRfcStats error:', error)
    stats.value = { total: 0, diajukan: 0, diproses: 0, production: 0 }
  }
}

async function loadRfcs(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const q = search.value?.trim() ? `q=${encodeURIComponent(search.value.trim())}&` : ''
    const resp = await http.get(`/rfc?${q}per_page=${pagination.value.perPage}&page=${page}`)
    if (resp.data.data) {
      const meta = resp.data.meta || resp.data
      rfcs.value = resp.data.data
      pagination.value = {
        currentPage: Number(meta.current_page) || page,
        lastPage: Number(meta.last_page) || 1,
        perPage: Number(meta.per_page) || pagination.value.perPage || 30,
        total: Number(meta.total) || 0,
      }
    } else {
      rfcs.value = resp.data || []
    }
  } catch (error) {
    warnDev('[PengelolaRfc] loadRfcs error:', error)
    loadError.value = 'Daftar RFC belum dapat dimuat. Periksa koneksi lalu coba lagi.'
    toast.push('Gagal memuat data RFC', 'error')
  } finally {
    loading.value = false
  }
}

async function loadActiveApps() {
  try {
    const resp = await http.get('/aplikasi?status=deployed_production&per_page=100')
    appsActive.value = (resp.data.data || resp.data || []).map((a) => ({
      id: a.id,
      name: a.nama_aplikasi || a.nama_layanan || a.nama_singkat || `Aplikasi #${a.id}`,
    }))
  } catch (error) {
    warnDev('[PengelolaRfc] loadActiveApps error:', error)
    appsActive.value = []
  }
}

onMounted(async () => {
  await Promise.all([loadRfcs(), loadActiveApps(), loadRfcStats()])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

function changePage(page) {
  loadRfcs(page)
}

function rowNumber(idx) {
  return ((pagination.value.currentPage - 1) * pagination.value.perPage) + idx + 1
}

watch(editing, (val) => {
  if (val) {
    rfcStep.value = 1
    rfcFile.value = null
    selectedApp.value = val.aplikasi_id
    selectedTipe.value = val.tipe_rfc
    selectedPelaksana.value = val.pelaksana || 'Internal Pusdatik'
    selectedStatus.value = val.status_tindaklanjut || 'Analisa Desain'
  } else {
    resetForm()
  }
}, { immediate: true })



async function submitRfc() {
  if (savingRfc.value) return
  if (!validateRfcData()) return

  if (!editing.value && !rfcFile.value) {
    toast.push('Formulir RFC wajib diunggah', 'error')
    return
  }

  savingRfc.value = true
  const fd = new FormData()
  fd.append('aplikasi_id', editing.value?.aplikasi_id || selectedApp.value)
  fd.append('tipe_rfc', selectedTipe.value)
  fd.append('pelaksana', selectedPelaksana.value)
  fd.append('status_tindaklanjut', selectedStatus.value)
  if (rfcFile.value) fd.append('formulir_rfc', rfcFile.value)
  try {
    if (editing.value) {
      fd.append('_method', 'PUT')
      await http.post(`/rfc/${editing.value.id}`, fd)
      toast.push('RFC berhasil diperbarui!', 'success')
    } else {
      await http.post('/rfc', fd)
      toast.push('RFC berhasil ditambahkan!', 'success')
    }
    showForm.value = false
    editing.value = null
    await Promise.all([loadRfcs(pagination.value.currentPage), loadRfcStats()])
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal menyimpan RFC'
    toast.push(message, 'error')
    warnDev('[PengelolaRfc] submitRfc error:', error)
  } finally {
    savingRfc.value = false
  }
}

function rfcStatusTone(status) {
  const badgeClass = getRfcStatusBadgeClass(status)
  if (badgeClass.includes('success')) return 'success'
  if (badgeClass.includes('danger')) return 'danger'
  if (badgeClass.includes('warning')) return 'warning'
  return ''
}

function handleFormModalChange(open) {
  if (!open) closeForm()
}

</script>

<template>
  <div class="ui-page">
    <PageHeader
      eyebrow="Pengelola Aplikasi"
      title="Kelola RFC"
      description="Pantau dan tindak lanjuti seluruh perubahan aplikasi dalam satu antrian."
    >
      <template #actions>
        <Button hierarchy="primary" size="lg" :prefix-icon="IconPlus" @click="openAdd">
          Tambah RFC
        </Button>
      </template>
    </PageHeader>

    <div class="ui-page-content">
      <section class="ui-metric-grid" aria-label="Ringkasan RFC">
        <MetricCard label="Total RFC" :value="stats.total" icon="file-text" tone="blue" />
        <MetricCard label="Diajukan" :value="stats.diajukan" icon="file" tone="amber" />
        <MetricCard label="Diproses" :value="stats.diproses" icon="code" tone="violet" />
        <MetricCard label="Production" :value="stats.production" icon="check-circle" tone="green" />
      </section>

      <section class="ui-panel" aria-labelledby="rfc-list-title">
        <header class="ui-panel-header">
          <div>
            <h2 id="rfc-list-title">Daftar RFC</h2>
            <p class="ui-table-subtitle">{{ pagination.total }} perubahan tercatat</p>
          </div>
          <div class="ui-panel-actions">
            <SearchField
              v-model="search"
              label="Cari RFC"
              placeholder="Cari RFC"
              @update:model-value="scheduleSearch"
            />
          </div>
        </header>

        <AsyncState
          :loading="loading"
          :error="loadError"
          :empty="rfcs.length === 0"
          :empty-icon="hasActiveSearch ? 'search' : 'file-text'"
          :empty-title="hasActiveSearch ? 'RFC tidak ditemukan' : 'Belum ada RFC'"
          :empty-description="hasActiveSearch
            ? 'Coba kata kunci lain atau hapus pencarian.'
            : 'Belum ada Request for Change yang tercatat dalam sistem.'"
          @retry="loadRfcs(pagination.currentPage)"
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
                <th scope="col">Tipe RFC</th>
                <th scope="col">Pelaksana</th>
                <th scope="col">Status</th>
                <th scope="col" class="ui-table-actions"><span class="sr-only">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in rfcs" :key="item.id">
                <td data-label="Nomor" data-hide-mobile="true" class="col-num">{{ rowNumber(idx) }}</td>
                <td data-primary="true">
                  <button type="button" class="ui-table-link ui-link-button" @click="openDetail(item)">
                    {{ item.aplikasi?.nama_aplikasi || '-' }}
                  </button>
                  <span class="ui-table-subtitle">RFC #{{ item.id }}</span>
                </td>
                <td data-label="Tipe RFC">{{ item.tipe_rfc || '-' }}</td>
                <td data-label="Pelaksana">{{ item.pelaksana || '-' }}</td>
                <td data-label="Status">
                  <StatusBadge :tone="rfcStatusTone(item.status_tindaklanjut)">
                    {{ item.status_tindaklanjut || '-' }}
                  </StatusBadge>
                </td>
                <td class="ui-table-actions">
                  <IconActionCell :label="`Aksi RFC ${item.aplikasi?.nama_aplikasi || item.id}`">
                    <IconActionButton label="Lihat detail" icon="eye" @click="openDetail(item)" />
                    <IconActionButton label="Edit RFC" icon="edit" @click="openEdit(item)" />
                    <IconActionButton label="Hapus RFC" icon="trash" tone="danger" @click="confirmDelete(item)" />
                  </IconActionCell>
                </td>
              </tr>
            </tbody>
          </DataTable>
        </AsyncState>

        <PaginationBar
          :page="pagination.currentPage"
          :last-page="pagination.lastPage"
          :total="pagination.total"
          item-label="RFC"
          @change="changePage"
        />
      </section>
    </div>

      <Modal
        :model-value="showForm"
        :title="editing ? 'Edit RFC' : 'Tambah RFC'"
        description="Lengkapi data perubahan dan formulir resmi RFC."
        size="lg"
        variant="centered"
        :persistent="savingRfc"
        @update:model-value="handleFormModalChange"
      >
        <Stepper :steps="rfcSteps" :current-step="rfcStep - 1" orientation="horizontal" />
        <form class="rfc-form idds-rfc-form" @submit.prevent="rfcStep === 1 ? nextRfcStep() : submitRfc()">
          <template v-if="rfcStep === 1">
            <div class="form-row idds-form-grid">
              <IddsSelect
                v-model="selectedApp"
                :options="applicationOptions"
                label="Nama aplikasi"
                placeholder="Cari atau pilih aplikasi"
                :disabled="!!editing || savingRfc"
                required
                width="100%"
              />
              <IddsSelect
                v-model="selectedTipe"
                :options="rfcTypeOptions"
                label="Tipe RFC"
                placeholder="Pilih tipe RFC"
                required
                width="100%"
              />
              <IddsSelect
                v-model="selectedPelaksana"
                :options="executorOptions"
                label="Pelaksana"
                placeholder="Pilih pelaksana"
                required
                width="100%"
              />
              <IddsSelect
                v-model="selectedStatus"
                :options="statusOptions"
                label="Status"
                placeholder="Pilih status"
                required
                width="100%"
              />
            </div>
            <div class="rfc-modal-actions">
              <Button hierarchy="secondary" size="lg" type="button" :disabled="savingRfc" @click="closeForm">Batal</Button>
              <Button hierarchy="primary" size="lg" type="button" :suffix-icon="IconArrowRight" :disabled="savingRfc" @click="nextRfcStep">Lanjut</Button>
            </div>
          </template>

          <template v-else>
            <section class="idds-upload-section" aria-labelledby="pengelola-rfc-upload-title">
              <div>
                <h4 id="pengelola-rfc-upload-title">Formulir RFC</h4>
                <p>File wajib untuk RFC baru. Saat mengedit, pilih file hanya jika formulir perlu diganti.</p>
              </div>
              <div class="idds-inline-links">
                <a href="/templates/Formulir-RFC.xlsx" class="rfc-template-link" target="_blank" rel="noopener">Buka template formulir RFC</a>
                <a v-if="editing?.formulir_url" :href="editing.formulir_url" class="rfc-template-link" target="_blank" rel="noopener">Lihat formulir saat ini</a>
              </div>
              <SingleFileUpload
                title="Pilih formulir RFC"
                description="PDF, DOC, DOCX, XLS, atau XLSX maksimal 10 MB"
                accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                :allowed-extensions="['pdf', 'doc', 'docx', 'xls', 'xlsx']"
                :max-size="10 * 1024 * 1024"
                :validate-magic-number="true"
                :disabled="savingRfc"
                @change="onRfcFileChange"
                @remove="removeRfcFile"
              />
            </section>
            <div class="rfc-modal-actions">
              <Button hierarchy="secondary" size="lg" type="button" :prefix-icon="IconArrowLeft" :disabled="savingRfc" @click="previousRfcStep">Kembali</Button>
              <Button hierarchy="primary" size="lg" type="submit" :prefix-icon="IconCheck" :disabled="savingRfc || (!editing && !rfcFile)">
                {{ savingRfc ? 'Menyimpan...' : (editing ? 'Simpan RFC' : 'Kirim RFC') }}
              </Button>
            </div>
          </template>
        </form>
      </Modal>

      <ConfirmationDrawer
        v-model="showDeleteModal"
        title="Hapus RFC"
        description="RFC dan formulir yang terkait akan dihapus permanen. Tindakan ini tidak dapat dibatalkan."
        :subject="deleteTarget?.aplikasi?.nama_aplikasi || 'RFC'"
        confirm-label="Hapus RFC"
        :loading="deletingRfc"
        @confirm="deleteRfc"
        @cancel="closeDeleteModal"
      />
  </div>
 </template>

<style scoped>
.rfc-form-modal {
  width: min(760px, calc(100vw - 32px));
  max-width: 760px;
  max-height: calc(100vh - 32px);
}

.col-aksi {
  width: 300px;
}

.rfc-form {
  display: flex;
  flex-direction: column;
}

.rfc-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
  padding: 0 20px;
}

.rfc-step {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ina-content-tertiary);
  transition: all 0.3s ease;
  white-space: nowrap;
}

.rfc-step span {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--ina-background-secondary);
  border: 1px solid var(--ina-stroke-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  transition: all 0.3s ease;
  line-height: var(--idds-caption-line);
}

.rfc-step.active { color: var(--ina-content-primary); }
.rfc-step.done { color: #10b981; }

.rfc-step.active span {
  background: var(--ina-primary-primary);
  border-color: var(--ina-primary-primary);
  color: #fff;
}

.rfc-step.done span {
  background: #10b981;
  border-color: #10b981;
  color: #fff;
}

.rfc-step-line {
  flex: 1;
  height: 2px;
  background: var(--ina-stroke-primary);
  margin: 0 16px;
  border-radius: 2px;
  transition: all 0.3s ease;
}

.rfc-step-line.active { background: #10b981; }

.rfc-upload-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.rfc-upload-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.rfc-upload-icon {
  flex-shrink: 0;
  width: 48px;
  height: 48px;
  border-radius: 10px;
  background: var(--ina-primary-50, #eff6ff);
  border: 1px solid rgba(59, 130, 246, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ina-primary-primary, #2563eb);
}

.rfc-upload-copy h4 {
  margin: 0 0 6px;
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  color: var(--ina-content-primary);
  line-height: var(--idds-body-small-line);
}

.rfc-upload-copy p {
  margin: 0;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.rfc-template-row {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--ina-content-secondary);
}

.rfc-template-link {
  font-size: var(--idds-caption-size);
  color: var(--ina-primary-primary);
  text-decoration: none;
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
}

.rfc-template-link:hover {
  text-decoration: underline;
}

.existing-file-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  color: var(--ina-primary-700);
  font-weight: var(--idds-weight-bold);
  text-decoration: none;
}

.existing-file-link:hover {
  text-decoration: underline;
}

.rfc-upload-area {
  border: 2px dashed var(--ina-stroke-primary);
  border-radius: 10px;
  background: var(--ina-background-primary);
  min-height: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.2s, background 0.2s;
}

.rfc-upload-area:hover {
  border-color: var(--ina-primary-primary);
  background: var(--ina-primary-50, #eff6ff);
}

.rfc-upload-area.has-file {
  border-style: solid;
  border-color: #10b981;
  background: #f0fdf4;
}

.rfc-upload-label {
  width: 100%;
  min-height: auto;
  padding: 32px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  color: var(--ina-content-secondary);
  text-align: center;
}

.rfc-upload-label strong {
  color: var(--ina-primary-700);
  font-size: var(--idds-body-small-size);
  line-height: var(--idds-body-small-line);
}

.rfc-upload-label span {
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.rfc-upload-area input[type='file'] {
  display: none;
}

.rfc-file-preview {
  width: 100%;
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 20px;
  color: var(--ina-primary-700);
}

.rfc-file-name,
.rfc-file-size {
  display: block;
}

.rfc-file-name {
  font-weight: var(--idds-weight-bold);
  color: var(--ina-content-primary);
}

.rfc-file-size {
  margin-top: 3px;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.rfc-remove-file-btn {
  width: 38px;
  height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #fecaca;
  border-radius: 10px;
  background: #fef2f2;
  color: #dc2626;
  cursor: pointer;
}

.rfc-detail-modal {
  width: min(720px, calc(100vw - 32px));
  max-width: 720px;
}

.rfc-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.rfc-detail-grid > div {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 14px;
  background: #fff;
}

.rfc-detail-grid span {
  display: block;
  margin-bottom: 6px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  text-transform: uppercase;
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-caption-small-line);
}

.rfc-detail-grid strong,
.rfc-detail-grid p {
  margin: 0;
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.rfc-detail-full {
  grid-column: 1 / -1;
}

.required-mark {
  color: var(--ina-negative-600);
  font-weight: var(--idds-weight-bold);
}

.rfc-modal-actions {
  display: flex;
  justify-content: stretch;
  gap: 10px;
  padding-top: 8px;
}

.rfc-modal-actions .btn {
  flex: 1;
  justify-content: center;
  margin-top: 0;
}

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin: 12px 20px 16px; }

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

.stat-icon-wrap.bg-blue { background: #eff6ff; color: #3b82f6; }
.stat-icon-wrap.bg-green { background: #f0fdf4; color: #10b981; }
.stat-icon-wrap.bg-amber { background: #fffbeb; color: #f59e0b; }
.stat-icon-wrap.bg-red { background: #fef2f2; color: #ef4444; }

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
  .rfc-form-modal {
    width: min(100vw - 20px, 760px);
    max-height: calc(100vh - 20px);
  }
  .rfc-form-modal .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
  .rfc-modal-actions {
    flex-direction: column-reverse;
  }
  .rfc-modal-actions .btn {
    width: 100%;
    justify-content: center;
  }
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

  .data-card-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .data-card-head-actions {
    width: 100%;
    flex-direction: column;
  }

}

/* Searchable Combobox */
.combobox-wrapper {
  position: relative;
}

.combobox-input {
  width: 100%;
  padding: 10px 36px 10px 12px;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 6px;
  background: var(--ina-background-primary);
  font-size: var(--idds-caption-size);
  color: var(--ina-content-primary);
  cursor: text;
  transition: border-color 0.15s;
  line-height: var(--idds-caption-line);
}

.combobox-input:focus {
  outline: none;
  border-color: var(--ina-primary-primary);
  box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.08);
}

.combobox-input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background: var(--ina-background-secondary, #f5f5f5);
}

.combobox-chevron {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--ina-content-secondary);
  pointer-events: none;
}

.combobox-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--ina-background-primary);
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  max-height: 200px;
  overflow-y: auto;
  z-index: 100;
  padding: 4px;
}

.combobox-dropdown::-webkit-scrollbar {
  width: 6px;
}

.combobox-dropdown::-webkit-scrollbar-track {
  background: transparent;
}

.combobox-dropdown::-webkit-scrollbar-thumb {
  background: var(--ina-stroke-primary);
  border-radius: 3px;
}

.combobox-option {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 12px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-primary);
  cursor: pointer;
  transition: background 0.1s;
  line-height: var(--idds-caption-line);
}

.combobox-option:hover {
  background: var(--ina-background-tertiary);
}

.combobox-option.active {
  background: rgba(30, 58, 138, 0.08);
  color: var(--ina-primary-primary);
  font-weight: var(--idds-weight-semibold);
}

.combobox-empty {
  padding: 12px;
  text-align: center;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-line);
}
</style>


