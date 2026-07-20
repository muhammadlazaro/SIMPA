<script setup>
import {
  Button,
  Modal,
  SingleFileUpload,
  Stepper,
  TextField,
} from '@idds/vue'
import { IconArrowLeft, IconArrowRight, IconCheck, IconDownload } from '@tabler/icons-vue'
import { ref, watch, computed } from 'vue'
import http from '../lib/http'
import { useToastStore } from '../stores/toast'
import { formatDate, formatRelativeTime } from '../utils/dateHelper'
import { resolveIddsFileSelection } from '../utils/fileUpload'
import { warnDev } from '../utils/logger'
import Icons from './Icons.vue'
import IddsSelect from './IddsSelect.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  app: { type: Object, default: null }
})
const emit = defineEmits(['close', 'saved'])

const toast = useToastStore()

const loading = ref(false)
const errors = ref({})
const EMPTY_FORM = {
  id: null,
  nama_layanan: '',
  nama_singkat: '',
  nama_aplikasi: '',
  jenis_layanan_aplikasi: '',
  kode_unitOrganisasi: '',
  tipe_akuisisi: ''
}

const form = ref({ ...EMPTY_FORM })

// Step 3: file state
const formulirFile = ref(null)
const uploadingDoc = ref(false)
function onFormulirFileChange(file, validation) {
  const selection = resolveIddsFileSelection(file, validation)
  formulirFile.value = selection.file

  if (selection.error) {
    toast.push(selection.error, 'error')
  }
}

function removeFormuliFile() {
  formulirFile.value = null
}

function openFormTemplate() {
  window.open(
    '/templates/P22-Formulir-Usulan-Pengembangan-Aplikasi.pdf',
    '_blank',
    'noopener,noreferrer',
  )
}

const step = ref(1)
const stepItems = computed(() => [
  { label: 'Identitas layanan' },
  { label: 'Detail teknis' },
  ...(!form.value.id ? [{ label: 'Formulir pengajuan' }] : []),
])

const errorSteps = computed(() => {
  const items = []
  if (errors.value.nama_layanan || errors.value.nama_singkat || errors.value.nama_aplikasi) items.push(0)
  if (errors.value.jenis_layanan_aplikasi || errors.value.kode_unitOrganisasi || errors.value.tipe_akuisisi) items.push(1)
  return items
})

const jenisLayananOptions = [
  { label: 'Publik', value: 'publik' },
  { label: 'Internal', value: 'internal' },
  { label: 'Pendukung', value: 'pendukung' },
]

const tipeAkuisisiOptions = [
  { label: 'Custom-Made', value: 'Custom-Made' },
  { label: 'Off-The-Shelf', value: 'Off-The-Shelf' },
]

watch(() => props.app, (val) => {
  form.value = val ? { ...val } : { ...EMPTY_FORM }
  step.value = 1
  formulirFile.value = null
}, { immediate: true })

watch(() => props.show, (val) => {
  if (!val) {
    step.value = 1
    formulirFile.value = null
    errors.value = {}
  }
})

function close() {
  emit('close')
  errors.value = {}
  step.value = 1
  formulirFile.value = null
}

function nextStep() {
  errors.value = {}
  if (!form.value.nama_layanan?.trim()) {
    errors.value.nama_layanan = 'Nama Layanan wajib diisi'
  }
  if (!form.value.nama_singkat?.trim()) {
    errors.value.nama_singkat = 'Nama Singkat wajib diisi'
  } else if (form.value.nama_singkat.length > 10) {
    errors.value.nama_singkat = 'Nama Singkat maksimal 10 karakter'
  }
  if (!form.value.nama_aplikasi?.trim()) {
    errors.value.nama_aplikasi = 'Nama Aplikasi wajib diisi'
  }
  
  if (Object.keys(errors.value).length === 0) {
    step.value = 2
  }
}

function nextStep2() {
  errors.value = {}
  if (!form.value.jenis_layanan_aplikasi) {
    errors.value.jenis_layanan_aplikasi = 'Jenis Layanan wajib dipilih'
  }
  if (!form.value.kode_unitOrganisasi?.trim()) {
    errors.value.kode_unitOrganisasi = 'Kode Unit Organisasi wajib diisi'
  }
  if (!form.value.tipe_akuisisi) {
    errors.value.tipe_akuisisi = 'Tipe Akuisisi wajib dipilih'
  }
  if (Object.keys(errors.value).length === 0) {
    // Edit mode: langsung submit tanpa step 3
    if (form.value.id) {
      handleSubmit()
    } else {
      step.value = 3
    }
  }
}

function validateStep2() {
  errors.value = {}
  if (!form.value.jenis_layanan_aplikasi) {
    errors.value.jenis_layanan_aplikasi = 'Jenis Layanan wajib dipilih'
  }
  if (!form.value.kode_unitOrganisasi?.trim()) {
    errors.value.kode_unitOrganisasi = 'Kode Unit Organisasi wajib diisi'
  }
  if (!form.value.tipe_akuisisi) {
    errors.value.tipe_akuisisi = 'Tipe Akuisisi wajib dipilih'
  }
  return Object.keys(errors.value).length === 0
}

async function handleSubmit() {
  if (!validateStep2()) {
    toast.push('Mohon lengkapi semua field yang wajib diisi', 'error')
    return
  }

  loading.value = true
  try {
    const fd = new FormData()
    fd.append('nama_layanan', form.value.nama_layanan)
    fd.append('nama_singkat', form.value.nama_singkat)
    fd.append('nama_aplikasi', form.value.nama_aplikasi)
    fd.append('jenis_layanan_aplikasi', form.value.jenis_layanan_aplikasi)
    fd.append('kode_unitOrganisasi', form.value.kode_unitOrganisasi)
    fd.append('tipe_akuisisi', form.value.tipe_akuisisi)

    let appId = form.value.id
    if (form.value.id) {
      fd.append('_method', 'PUT')
      await http.post(`/aplikasi/${form.value.id}`, fd)
      toast.push('Aplikasi berhasil diupdate!', 'success')
    } else {
      const res = await http.post('/aplikasi', fd)
      appId = res.data?.data?.aplikasi?.id || res.data?.data?.id || res.data?.id
      // Upload formulir jika ada file dipilih
      if (formulirFile.value && appId) {
        uploadingDoc.value = true
        try {
          const docFd = new FormData()
          docFd.append('document_type', 'formulir_pengajuan')
          docFd.append('file', formulirFile.value)
          await http.post(`/aplikasi/${appId}/documents`, docFd)
        } catch (docErr) {
          warnDev('[AplikasiFormModal] doc upload error:', docErr)
          toast.push('Pengajuan berhasil, namun Formulir Pengajuan gagal diunggah. Silakan upload ulang di halaman detail.', 'warning')
        } finally {
          uploadingDoc.value = false
        }
      }
      toast.push('Pengajuan aplikasi berhasil dikirim.', 'success')
    }

    emit('saved')
    emit('close')
  } catch (error) {
    warnDev('[AplikasiFormModal] save error:', error)
    let errorMsg = 'Gagal menyimpan aplikasi'
    if (error.response?.data?.errors) {
      errorMsg = Object.values(error.response.data.errors).flat().join(', ')
    } else if (error.response?.data?.message) {
      errorMsg = error.response.data.message
    } else if (error.message) {
      errorMsg = error.message
    }
    toast.push(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

</script>

<template>
  <Modal
    :model-value="show"
    :title="form.id ? 'Edit aplikasi' : 'Tambah aplikasi baru'"
    :description="form.id ? 'Perbarui identitas dan detail teknis aplikasi.' : 'Lengkapi data aplikasi dalam tiga tahap.'"
    size="lg"
    :show-close-button="true"
    :show-footer="false"
    close-label="Tutup formulir aplikasi"
    :close-on-backdrop="!loading && !uploadingDoc"
    :close-on-escape="!loading && !uploadingDoc"
    :persistent="loading || uploadingDoc"
    padding-body="24px"
    @update:model-value="($event) => { if (!$event) close() }"
  >
    <div class="app-form-modal">
      <Stepper
        class="application-stepper"
        :steps="stepItems"
        :current-step="step - 1"
        :error-steps="errorSteps"
        orientation="horizontal"
        tabindex="0"
        aria-label="Tahapan formulir aplikasi"
      />

      <form @submit.prevent>
        <transition name="fade-slide" mode="out-in">
          <!-- STEP 1: Identitas Layanan -->
          <div v-if="step === 1" class="wizard-step" key="step1">
            <TextField
              v-model="form.nama_layanan"
              label="Nama layanan"
              placeholder="Contoh: Layanan Identitas Digital"
              size="lg"
              :required="true"
              :max-length="100"
              :status="errors.nama_layanan ? 'error' : 'neutral'"
              :status-message="errors.nama_layanan || ''"
            />
            <div class="form-row">
              <TextField
                v-model="form.nama_singkat"
                label="Nama singkat"
                helper-text="Maksimal 10 karakter."
                placeholder="Contoh: SIDIG"
                size="lg"
                :required="true"
                :max-length="10"
                :show-char-count="true"
                :status="errors.nama_singkat ? 'error' : 'neutral'"
                :status-message="errors.nama_singkat || ''"
              />
              <TextField
                v-model="form.nama_aplikasi"
                label="Nama aplikasi"
                placeholder="Contoh: Sistem Identitas Digital"
                size="lg"
                :required="true"
                :max-length="255"
                :status="errors.nama_aplikasi ? 'error' : 'neutral'"
                :status-message="errors.nama_aplikasi || ''"
              />
            </div>
          </div>

          <!-- STEP 2: Detail Teknis -->
          <div v-else-if="step === 2" class="wizard-step" key="step2">
            <div class="form-row">
              <IddsSelect
                v-model="form.jenis_layanan_aplikasi"
                label="Jenis layanan aplikasi"
                :options="jenisLayananOptions"
                placeholder="Pilih jenis layanan"
                size="lg"
                width="100%"
                panel-width="100%"
                :required="true"
                :status="errors.jenis_layanan_aplikasi ? 'error' : 'neutral'"
                :status-message="errors.jenis_layanan_aplikasi || ''"
              />
              <TextField
                v-model="form.kode_unitOrganisasi"
                label="Kode unit organisasi"
                placeholder="Contoh: BSSN-01"
                size="lg"
                :required="true"
                :max-length="255"
                :status="errors.kode_unitOrganisasi ? 'error' : 'neutral'"
                :status-message="errors.kode_unitOrganisasi || ''"
              />
            </div>
            <div class="form-row">
              <IddsSelect
                v-model="form.tipe_akuisisi"
                class="form-group-wide"
                label="Tipe akuisisi"
                :options="tipeAkuisisiOptions"
                placeholder="Pilih tipe akuisisi"
                size="lg"
                width="100%"
                panel-width="100%"
                :required="true"
                :status="errors.tipe_akuisisi ? 'error' : 'neutral'"
                :status-message="errors.tipe_akuisisi || ''"
              />
            </div>
          </div>

          <!-- STEP 3: Upload Formulir Pengajuan (hanya untuk new app) -->
          <div v-else-if="step === 3" class="wizard-step" key="step3">
            <div class="step3-header">
              <div class="step3-icon">
                <Icons name="file-text" :size="28" />
              </div>
              <div>
                <h4 class="step3-title">Unggah formulir pengajuan</h4>
                <p class="step3-desc">Unggah formulir pengajuan resmi (PDF / DOC). Dokumen ini <strong>wajib diunggah</strong> sebagai syarat pengajuan aplikasi baru.</p>
              </div>
            </div>

            <!-- Template download -->
            <div class="step3-template-row">
              <Button
                hierarchy="link"
                size="md"
                :prefix-icon="IconDownload"
                @click="openFormTemplate"
              >
                Buka template formulir pengajuan
              </Button>
            </div>

            <SingleFileUpload
              title="Pilih formulir pengajuan"
              description="PDF, DOC, atau DOCX; maksimal 10 MB."
              accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              :allowed-extensions="['pdf', 'doc', 'docx']"
              :max-size="10 * 1024 * 1024"
              :validate-magic-number="true"
              :disabled="loading || uploadingDoc"
              :status="uploadingDoc ? 'uploading' : (formulirFile ? 'success' : 'idle')"
              @change="onFormulirFileChange"
              @remove="removeFormuliFile"
            />

            <p class="step3-required-note">
              <Icons name="alert-circle" :size="13" />
              Formulir pengajuan wajib diunggah sebelum pengajuan dapat diproses oleh Pengelola Aplikasi.
            </p>
          </div>
        </transition>

        <!-- Navigation Buttons -->
        <div class="modal-actions">
          <template v-if="step === 1">
            <Button hierarchy="secondary" size="lg" type="button" @click="close">Batal</Button>
            <Button hierarchy="primary" size="lg" type="button" :suffix-icon="IconArrowRight" @click="nextStep">
              Lanjut
            </Button>
          </template>
          <template v-else-if="step === 2">
            <Button hierarchy="secondary" size="lg" type="button" :prefix-icon="IconArrowLeft" :disabled="loading" @click="step = 1">
              Kembali
            </Button>
            <Button
              v-if="form.id"
              hierarchy="primary"
              size="lg"
              type="button"
              :disabled="loading"
              :prefix-icon="IconCheck"
              @click="nextStep2"
            >
              {{ loading ? 'Menyimpan' : 'Simpan perubahan' }}
            </Button>
            <Button
              v-else
              hierarchy="primary"
              size="lg"
              type="button"
              :suffix-icon="IconArrowRight"
              :disabled="loading"
              @click="nextStep2"
            >
              Lanjut
            </Button>
          </template>
          <template v-else-if="step === 3">
            <Button hierarchy="secondary" size="lg" type="button" :prefix-icon="IconArrowLeft" :disabled="loading || uploadingDoc" @click="step = 2">
              Kembali
            </Button>
            <Button
              hierarchy="primary"
              size="lg"
              type="button"
              :prefix-icon="IconCheck"
              :disabled="loading || uploadingDoc || !formulirFile"
              @click="handleSubmit"
            >
              {{ uploadingDoc ? 'Mengunggah formulir' : (loading ? 'Mengirim pengajuan' : 'Ajukan aplikasi') }}
            </Button>
          </template>
        </div>
      </form>
      
      <!-- Timestamp Info (only shown when editing) -->
      <div v-if="form.id" class="modal-footer">
        <div class="timestamp-info">
          <div v-if="form.created_at" class="timestamp-item">
            <span class="timestamp-label">Dibuat:</span>
            <span 
              class="timestamp-value" 
              :title="`${formatDate(form.created_at)}`">
              {{ formatRelativeTime(form.created_at) }} oleh {{ form.creator?.name || 'System' }}
            </span>
          </div>
          <div v-if="form.updated_at" class="timestamp-item">
            <span class="timestamp-label">Diupdate:</span>
            <span 
              class="timestamp-value" 
              :title="`${formatDate(form.updated_at)}`">
              {{ formatRelativeTime(form.updated_at) }} oleh {{ form.updater?.name || 'System' }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </Modal>
</template>

<style scoped>
.app-form-modal {
  display: grid;
  gap: var(--ina-spacing-5);
}

.application-stepper {
  margin-bottom: var(--ina-spacing-2);
}

.application-stepper:focus-visible {
  outline: 2px solid var(--ina-primary-primary);
  outline-offset: 4px;
}

.application-stepper :deep(.ina-stepper__label),
.app-form-modal :deep(.ina-text-field__char-count) {
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.required-mark {
  color: var(--ina-negative-600);
  font-weight: var(--idds-weight-bold);
}

.field-help {
  display: block;
  margin-top: 4px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 24px;
}

.modal-actions :deep(.ina-button) {
  margin-top: 0;
  flex: 1;
  justify-content: center;
}

.modal-actions :deep(.ina-button--primary) {
  background-color: var(--ina-primary-600);
}

.modal-actions :deep(.ina-button--primary:hover) {
  background-color: var(--ina-primary-700);
}

/* Stepper UI */
.wizard-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
  padding: 0 20px;
}

.step-item {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ina-content-tertiary);
  transition: all 0.3s ease;
}

.step-item.active { color: var(--ina-content-primary); }
.step-item.completed { color: #10b981; }

.step-number {
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

.step-item.active .step-number {
  background: var(--ina-primary-primary);
  color: white;
  border-color: var(--ina-primary-primary);
}

.step-item.completed .step-number {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.step-label { font-size: var(--idds-caption-size); font-weight: var(--idds-weight-semibold); line-height: var(--idds-caption-line); }

.step-line {
  flex: 1;
  height: 2px;
  background: var(--ina-stroke-primary);
  margin: 0 16px;
  border-radius: 2px;
  transition: all 0.3s ease;
}

.step-line.active { background: #10b981; }

/* Transitions */
.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(15px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-15px);
}

.btn-cancel {
  background: rgba(55, 53, 47, 0.07);
  color: var(--ina-content-primary);
  border: 1px solid var(--ina-stroke-primary);
}

.btn-cancel:hover:not(:disabled) {
  background: rgba(55, 53, 47, 0.13);
}

.btn-skip {
  flex: 1;
  background: transparent;
  color: var(--ina-content-secondary);
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 6px;
  padding: 10px 16px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-medium);
  cursor: pointer;
  transition: all 0.2s;
  line-height: var(--idds-caption-line);
}
.btn-skip:hover:not(:disabled) {
  background: var(--ina-background-tertiary);
  color: var(--ina-content-primary);
}
.btn-skip:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-submit {
  flex: 1;
}

.form-group-wide {
  grid-column: 1 / -1;
}

.input-error {
  border-color: var(--ina-negative-600) !important;
  background-color: var(--ina-negative-50) !important;
}

.input-disabled {
  background-color: var(--ina-background-secondary) !important;
  cursor: not-allowed !important;
  color: var(--ina-content-secondary);
}

.char-counter-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  min-height: 18px;
  margin-top: 4px;
}

.char-counter {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-tertiary);
  margin-left: auto;
  transition: color 0.2s;
  line-height: var(--idds-caption-small-line);
}

.char-counter--warn {
  color: #c05c00;
  font-weight: var(--idds-weight-semibold);
}

.error-message {
  display: block;
  color: var(--ina-negative-600);
  font-size: var(--idds-caption-small-size);
  margin-top: 4px;
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-small-line);
}

.modal-footer {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--ina-stroke-primary);
}

.timestamp-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.timestamp-item {
  display: flex;
  gap: 8px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.timestamp-label {
  color: var(--ina-content-secondary);
  font-weight: var(--idds-weight-medium);
  min-width: 70px;
}

.timestamp-value {
  color: var(--ina-content-primary);
  cursor: default;
  border-bottom: 1px dotted rgba(55, 53, 47, 0.3);
  transition: all 0.15s ease;
}

.timestamp-value:hover {
  color: var(--ina-primary-primary);
  border-bottom-color: var(--ina-primary-primary);
}

:deep(.modal-content) { position: relative; }

/* ===== STEP 3 STYLES ===== */
.step3-header {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 20px;
}

.step3-icon {
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

.step3-title {
  margin: 0 0 6px;
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  color: var(--ina-content-primary);
  line-height: var(--idds-body-small-line);
}

.step3-desc {
  margin: 0;
  font-size: var(--idds-caption-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-line);
}

.step3-template-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 16px;
  color: var(--ina-content-secondary);
}

.step3-template-link {
  font-size: var(--idds-caption-size);
  color: var(--ina-primary-primary);
  text-decoration: none;
  font-weight: var(--idds-weight-medium);
  line-height: var(--idds-caption-line);
}
.step3-template-link:hover { text-decoration: underline; }

.step3-upload-area {
  border: 2px dashed var(--ina-stroke-primary);
  border-radius: 10px;
  background: var(--ina-background-primary);
  transition: border-color 0.2s, background 0.2s;
  margin-bottom: 14px;
}

.step3-upload-area:hover {
  border-color: var(--ina-primary-primary);
  background: var(--ina-primary-50, #eff6ff);
}

.step3-upload-area.has-file {
  border-style: solid;
  border-color: #10b981;
  background: #f0fdf4;
}

.step3-upload-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 20px;
  cursor: pointer;
  gap: 8px;
  position: relative;
}

.step3-upload-text {
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  color: var(--ina-content-primary);
  line-height: var(--idds-caption-line);
}

.step3-upload-hint {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
}

.step3-upload-icon {
  color: #94a3b8;
}

.step3-file-input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.step3-file-preview {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
}

.step3-file-icon {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border-radius: 6px;
  background: #d1fae5;
  border: 1px solid #6ee7b7;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #059669;
}

.step3-file-info {
  flex: 1;
  min-width: 0;
}

.step3-file-name {
  display: block;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  color: var(--ina-content-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: var(--idds-caption-line);
}

.step3-file-size {
  display: block;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  margin-top: 2px;
  line-height: var(--idds-caption-small-line);
}

.step3-remove-btn {
  flex-shrink: 0;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 4px;
  cursor: pointer;
  color: var(--ina-content-secondary);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}
.step3-remove-btn:hover {
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.3);
  color: #ef4444;
}

.step3-required-note {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  font-size: var(--idds-caption-small-size);
  color: #b45309;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 6px;
  padding: 8px 12px;
  margin: 0;
  line-height: var(--idds-caption-small-line);
}
.step3-required-note svg { flex-shrink: 0; margin-top: 1px; color: #b45309; }

@keyframes spin {
  to { transform: rotate(360deg); }
}
.spin { animation: spin 0.8s linear infinite; }

.spin-inline,
.submit-icon {
  display: inline-block;
  vertical-align: middle;
  margin-right: 4px;
}

@media (max-width: 640px) {
  .form-row {
    grid-template-columns: 1fr;
    gap: var(--ina-spacing-4);
  }

  .step3-header,
  .step3-file-preview {
    align-items: flex-start;
  }

  .step3-upload-label {
    padding: 28px 16px;
  }

  .modal-actions {
    flex-direction: column-reverse;
  }

  .modal-actions :deep(.ina-button) {
    width: 100%;
    justify-content: center;
  }
}
</style>
