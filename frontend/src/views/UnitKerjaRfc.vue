<script setup>
import {
  Alert,
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
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import http from '../lib/http'
import AsyncState from '../components/AsyncState.vue'
import DataTable from '../components/DataTable.vue'
import IddsSelect from '../components/IddsSelect.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useToastStore } from '../stores/toast'
import { getRfcStatusBadgeClass } from '../constants/status'
import { resolveIddsFileSelection } from '../utils/fileUpload'
import { warnDev } from '../utils/logger'

const toast = useToastStore()

const rfcs = ref([])
const productionApps = ref([])
const loading = ref(false)
const loadError = ref('')
const saving = ref(false)
const search = ref('')
const showForm = ref(false)
const selectedApp = ref('')
const selectedTipe = ref('')
const formStep = ref(1)
const rfcFile = ref(null)
const pagination = ref({ currentPage: 1, lastPage: 1, perPage: 30, total: 0 })
const stats = ref({ total: 0, diajukan: 0, diproses: 0, production: 0 })

let searchTimer = null

const hasActiveSearch = computed(() => !!search.value?.trim())
const canSubmit = computed(() => productionApps.value.length > 0)
const applicationOptions = computed(() => productionApps.value.map((app) => ({
  label: `${app.name}${app.layanan ? ` - ${app.layanan}` : ''}`,
  value: app.id,
})))
const rfcTypeOptions = [
  { label: 'Medium', value: 'Medium' },
  { label: 'Standar', value: 'Standar' },
  { label: 'Minor', value: 'Minor' },
  { label: 'Major', value: 'Major' },
  { label: 'Darurat', value: 'Darurat' },
]
const rfcSteps = [
  { label: 'Data RFC' },
  { label: 'Formulir RFC' },
]

onMounted(async () => {
  await Promise.all([loadRfcs(), loadProductionApps(), loadRfcStats()])
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})

async function loadRfcs(page = 1) {
  loading.value = true
  loadError.value = ''
  try {
    const q = search.value?.trim() ? `q=${encodeURIComponent(search.value.trim())}&` : ''
    const response = await http.get(`/rfc?${q}per_page=${pagination.value.perPage}&page=${page}`)

    if (response.data.data) {
      const meta = response.data.meta || response.data
      rfcs.value = response.data.data
      pagination.value = {
        currentPage: Number(meta.current_page) || page,
        lastPage: Number(meta.last_page) || 1,
        perPage: Number(meta.per_page) || pagination.value.perPage || 30,
        total: Number(meta.total) || 0,
      }
    } else {
      rfcs.value = response.data || []
      pagination.value.currentPage = page
    }
  } catch (error) {
    warnDev('[UnitKerjaRfc] loadRfcs error:', error)
    loadError.value = 'Daftar RFC belum dapat dimuat. Periksa koneksi lalu coba lagi.'
    toast.push('Tidak dapat memuat daftar RFC.', 'error', 4000)
  } finally {
    loading.value = false
  }
}

async function loadProductionApps() {
  try {
    const response = await http.get('/aplikasi?status=deployed_production&per_page=100')
    productionApps.value = (response.data.data || response.data || []).map((app) => ({
      id: app.id,
      name: app.nama_aplikasi || app.nama_layanan || app.nama_singkat || `Aplikasi #${app.id}`,
      layanan: app.nama_layanan,
    }))
  } catch (error) {
    warnDev('[UnitKerjaRfc] loadProductionApps error:', error)
    productionApps.value = []
  }
}

async function loadRfcStats() {
  try {
    const response = await http.get('/rfc/stats')
    stats.value = {
      total: Number(response.data?.data?.total) || 0,
      diajukan: Number(response.data?.data?.diajukan) || 0,
      diproses: Number(response.data?.data?.diproses) || 0,
      production: Number(response.data?.data?.production) || 0,
    }
  } catch (error) {
    warnDev('[UnitKerjaRfc] loadRfcStats error:', error)
    stats.value = { total: 0, diajukan: 0, diproses: 0, production: 0 }
  }
}

async function openForm() {
  resetForm()
  showForm.value = true
  await loadProductionApps()
}

function resetForm() {
  formStep.value = 1
  selectedApp.value = ''
  selectedTipe.value = ''
  rfcFile.value = null
}

function closeForm() {
  if (saving.value) return
  showForm.value = false
}

async function submitRfc() {
  if (saving.value) return
  if (!selectedApp.value || !selectedTipe.value) {
    toast.push('Pilih aplikasi dan tipe RFC terlebih dahulu.', 'error')
    return
  }
  if (!rfcFile.value) {
    toast.push('Formulir RFC wajib diunggah.', 'error')
    return
  }

  saving.value = true
  try {
    const fd = new FormData()
    fd.append('aplikasi_id', selectedApp.value)
    fd.append('tipe_rfc', selectedTipe.value)
    fd.append('formulir_rfc', rfcFile.value)

    await http.post('/rfc', fd)

    toast.push('Pengajuan RFC berhasil dikirim.', 'success')
    showForm.value = false
    await Promise.all([loadRfcs(1), loadRfcStats()])
  } catch (error) {
    const message = error.response?.data?.message || error.message || 'Gagal mengajukan RFC.'
    toast.push(message, 'error')
    warnDev('[UnitKerjaRfc] submitRfc error:', error)
  } finally {
    saving.value = false
  }
}

function nextStep() {
  if (!selectedApp.value || !selectedTipe.value) {
    toast.push('Pilih aplikasi dan tipe RFC terlebih dahulu.', 'error')
    return
  }
  formStep.value = 2
}

function previousStep() {
  formStep.value = 1
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

function changePage(page) {
  if (page === '...' || page < 1 || page > pagination.value.lastPage) return
  loadRfcs(page)
}

function rowNumber(idx) {
  return ((pagination.value.currentPage - 1) * pagination.value.perPage) + idx + 1
}

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
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
      eyebrow="Unit Kerja"
      title="Pengajuan RFC"
      description="Ajukan perubahan untuk aplikasi production milik unit kerja Anda."
    >
      <template #actions>
        <Button
          hierarchy="primary"
          size="lg"
          :prefix-icon="IconPlus"
          :disabled="!canSubmit"
          @click="openForm"
        >
          Ajukan RFC
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

      <Alert
        v-if="!canSubmit"
        variant="caution"
        title="Belum ada aplikasi production"
        message="RFC dapat diajukan setelah aplikasi milik unit kerja Anda berstatus production."
      />

      <section class="ui-panel" aria-labelledby="unit-rfc-list-title">
        <header class="ui-panel-header">
          <div>
            <h2 id="unit-rfc-list-title">Riwayat RFC Anda</h2>
            <p class="ui-table-subtitle">{{ pagination.total }} pengajuan perubahan</p>
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
            : 'Belum ada pengajuan perubahan untuk aplikasi production unit kerja Anda.'"
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
                <th scope="col">Status</th>
                <th scope="col">Tanggal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in rfcs" :key="item.id">
                <td data-label="Nomor" data-hide-mobile="true" class="col-num">{{ rowNumber(idx) }}</td>
                <td data-primary="true">
                  <span class="ui-table-primary">{{ item.aplikasi?.nama_aplikasi || '-' }}</span>
                  <span class="ui-table-subtitle">RFC #{{ item.id }}</span>
                </td>
                <td data-label="Tipe RFC">{{ item.tipe_rfc || '-' }}</td>
                <td data-label="Status">
                  <StatusBadge :tone="rfcStatusTone(item.status_tindaklanjut)">
                    {{ item.status_tindaklanjut || '-' }}
                  </StatusBadge>
                </td>
                <td data-label="Tanggal">{{ formatDate(item.created_at) }}</td>
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
      title="Ajukan RFC"
      description="Lengkapi data perubahan dan unggah formulir resmi."
      size="lg"
      variant="centered"
      :persistent="saving"
      @update:model-value="handleFormModalChange"
    >
      <Stepper :steps="rfcSteps" :current-step="formStep - 1" orientation="horizontal" />
      <form class="rfc-form idds-rfc-form" @submit.prevent="formStep === 1 ? nextStep() : submitRfc()">
        <template v-if="formStep === 1">
          <IddsSelect
            v-model="selectedApp"
            :options="applicationOptions"
            label="Aplikasi production"
            placeholder="Pilih aplikasi"
            :disabled="saving || productionApps.length === 0"
            required
            width="100%"
          />
          <IddsSelect
            v-model="selectedTipe"
            :options="rfcTypeOptions"
            label="Tipe RFC"
            placeholder="Pilih tipe RFC"
            :disabled="saving"
            required
            width="100%"
          />
          <div class="rfc-modal-actions">
            <Button hierarchy="secondary" size="lg" type="button" :disabled="saving" @click="closeForm">
              Batal
            </Button>
            <Button hierarchy="primary" size="lg" type="button" :suffix-icon="IconArrowRight" :disabled="saving" @click="nextStep">
              Lanjut
            </Button>
          </div>
        </template>

        <template v-else>
          <section class="idds-upload-section" aria-labelledby="unit-rfc-upload-title">
            <div>
              <h4 id="unit-rfc-upload-title">Formulir RFC</h4>
              <p>Unggah satu dokumen resmi berformat PDF, DOC, DOCX, XLS, atau XLSX maksimal 10 MB.</p>
            </div>
            <a href="/templates/Formulir-RFC.xlsx" class="rfc-template-link" target="_blank" rel="noopener">
              Buka template formulir RFC
            </a>
            <SingleFileUpload
              title="Pilih formulir RFC"
              description="PDF, DOC, DOCX, XLS, atau XLSX maksimal 10 MB"
              accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              :allowed-extensions="['pdf', 'doc', 'docx', 'xls', 'xlsx']"
              :max-size="10 * 1024 * 1024"
              :validate-magic-number="true"
              :disabled="saving"
              @change="onRfcFileChange"
              @remove="removeRfcFile"
            />
          </section>
          <div class="rfc-modal-actions">
            <Button hierarchy="secondary" size="lg" type="button" :prefix-icon="IconArrowLeft" :disabled="saving" @click="previousStep">
              Kembali
            </Button>
            <Button hierarchy="primary" size="lg" type="submit" :prefix-icon="IconCheck" :disabled="saving || !rfcFile">
              {{ saving ? 'Mengirim...' : 'Kirim pengajuan' }}
            </Button>
          </div>
        </template>
      </form>
    </Modal>
  </div>
</template>

<style scoped>
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

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
  margin: 12px 20px 16px;
}

.stat-card {
  position: relative;
  background: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 8px;
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.stat-card::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: transparent;
}

.stat-card.total::after { background: #3b82f6; }
.stat-card.submitted::after { background: #f59e0b; }
.stat-card.progress::after { background: #6366f1; }
.stat-card.done::after { background: #10b981; }

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

.bg-blue { background: #eff6ff; color: #3b82f6; }
.bg-green { background: #ecfdf5; color: #10b981; }
.bg-amber { background: #fffbeb; color: #f59e0b; }
.bg-indigo { background: #eef2ff; color: #4f46e5; }

.stat-value {
  font-size: var(--idds-heading-h3-size);
  font-weight: var(--idds-weight-bold);
  color: #111827;
  line-height: var(--idds-heading-h3-line);
}

.notice-card {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 20px 16px;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
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

.description-cell {
  max-width: 360px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.col-date {
  white-space: nowrap;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-line);
}

.global-empty {
  text-align: center;
  padding: 72px 24px;
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
  max-width: 360px;
  margin: 0 auto 24px;
  line-height: var(--idds-caption-line);
}

.rfc-form-modal {
  width: min(760px, calc(100vw - 32px));
  max-width: 760px;
  max-height: calc(100vh - 32px);
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
}

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

@media (max-width: 768px) {
  .uk-welcome-card {
    flex-direction: column;
    align-items: flex-start;
    margin: 0 12px 20px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
    margin: 12px 12px 16px;
  }

  .notice-card {
    margin: 0 12px 16px;
  }

  .uk-card-head {
    flex-direction: column;
    align-items: flex-start;
  }

  .rfc-modal-actions {
    flex-direction: column-reverse;
  }

  .rfc-modal-actions .btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
