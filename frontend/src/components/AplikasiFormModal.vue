<script setup>
import { ref, watch, computed } from 'vue'
import http from '../lib/http'
import { useToastStore } from '../stores/toast'
import { formatDate, formatRelativeTime } from '../utils/dateHelper'
import { warnDev } from '../utils/logger'
import Icons from './Icons.vue'

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
const formulirInput = ref(null)

function onFormuliFileChange(e) {
  formulirFile.value = e.target.files[0] || null
}

function removeFormuliFile() {
  formulirFile.value = null
  if (formulirInput.value) formulirInput.value.value = ''
}

const step = ref(1)

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
  if (!form.value.id) {
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
    fd.append('jenis_layanan_aplikasi', form.value.jenis_layanan_aplikasi)
    fd.append('kode_unitOrganisasi', form.value.kode_unitOrganisasi)
    fd.append('tipe_akuisisi', form.value.tipe_akuisisi)

    if (!form.value.id) {
      fd.append('nama_layanan', form.value.nama_layanan)
      fd.append('nama_singkat', form.value.nama_singkat)
      fd.append('nama_aplikasi', form.value.nama_aplikasi)
    }

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

// Computed label untuk tombol submit
const isNewApp = computed(() => !form.value.id)
</script>

<template>
  <div v-if="show" class="modal active" role="dialog" aria-modal="true" aria-labelledby="modal-title-aplikasi">
    <div class="modal-content app-form-modal">
      <div class="modal-header">
        <h3 :id="`modal-title-aplikasi`">{{ form.id ? 'Edit Aplikasi' : 'Tambah Aplikasi Baru' }}</h3>
        <button class="close-btn" @click="close" aria-label="Tutup modal">&times;</button>
      </div>

      <!-- Stepper: 3 step untuk new, 2 step untuk edit -->
      <div class="wizard-stepper">
        <!-- Step 1 -->
        <div class="step-item" :class="{ 'active': step >= 1, 'completed': step > 1 }">
          <div class="step-number">
            <Icons v-if="step > 1" name="check" :size="12" />
            <span v-else>1</span>
          </div>
          <div class="step-label">Identitas Layanan</div>
        </div>
        <div class="step-line" :class="{ 'active': step > 1 }"></div>
        <!-- Step 2 -->
        <div class="step-item" :class="{ 'active': step === 2, 'completed': step > 2 }">
          <div class="step-number">
            <Icons v-if="step > 2" name="check" :size="12" />
            <span v-else>2</span>
          </div>
          <div class="step-label">Detail Teknis</div>
        </div>
        <!-- Step 3 hanya untuk new app -->
        <template v-if="isNewApp">
          <div class="step-line" :class="{ 'active': step > 2 }"></div>
          <div class="step-item" :class="{ 'active': step === 3 }">
            <div class="step-number">3</div>
            <div class="step-label">Formulir Pengajuan</div>
          </div>
        </template>
      </div>

      <form @submit.prevent>
        <transition name="fade-slide" mode="out-in">
          <!-- STEP 1: Identitas Layanan -->
          <div v-if="step === 1" class="wizard-step" key="step1">
            <div class="form-group">
              <label>Nama Layanan <span class="required-mark">*</span></label>
              <input
                type="text"
                v-model="form.nama_layanan"
                :disabled="!!form.id"
                :class="{ 'input-error': errors.nama_layanan, 'input-disabled': !!form.id }"
                placeholder="Contoh: Layanan Identitas Digital"
                minlength="3"
                maxlength="100"
              />
              <small v-if="errors.nama_layanan" class="error-message">{{ errors.nama_layanan }}</small>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Nama Singkat <span class="required-mark">*</span></label>
                <input
                  type="text"
                  v-model="form.nama_singkat"
                  :disabled="!!form.id"
                  :class="{ 'input-error': errors.nama_singkat, 'input-disabled': !!form.id }"
                  placeholder="Maks. 10 karakter, contoh: SIDIG"
                  maxlength="10"
                />
                <small v-if="errors.nama_singkat" class="error-message">{{ errors.nama_singkat }}</small>
              </div>
              <div class="form-group">
                <label>Nama Aplikasi <span class="required-mark">*</span></label>
                <input
                  type="text"
                  v-model="form.nama_aplikasi"
                  :disabled="!!form.id"
                  :class="{ 'input-error': errors.nama_aplikasi, 'input-disabled': !!form.id }"
                  placeholder="Contoh: Sistem Identitas Digital"
                />
                <small v-if="errors.nama_aplikasi" class="error-message">{{ errors.nama_aplikasi }}</small>
              </div>
            </div>
          </div>

          <!-- STEP 2: Detail Teknis -->
          <div v-else-if="step === 2" class="wizard-step" key="step2">
            <div class="form-row">
              <div class="form-group">
                <label>Jenis Layanan Aplikasi <span class="required-mark">*</span></label>
                <select v-model="form.jenis_layanan_aplikasi" :class="{ 'input-error': errors.jenis_layanan_aplikasi }">
                  <option value="" disabled selected>-- Pilih Jenis --</option>
                  <option value="publik">Publik</option>
                  <option value="internal">Internal</option>
                  <option value="pendukung">Pendukung</option>
                </select>
                <small v-if="errors.jenis_layanan_aplikasi" class="error-message">{{ errors.jenis_layanan_aplikasi }}</small>
              </div>
              <div class="form-group">
                <label>Kode Unit Organisasi <span class="required-mark">*</span></label>
                <input
                  type="text"
                  v-model="form.kode_unitOrganisasi"
                  :class="{ 'input-error': errors.kode_unitOrganisasi }"
                  placeholder="Contoh: BSSN-01"
                />
                <small v-if="errors.kode_unitOrganisasi" class="error-message">{{ errors.kode_unitOrganisasi }}</small>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group form-group-wide">
                <label>Tipe Akuisisi <span class="required-mark">*</span></label>
                <select v-model="form.tipe_akuisisi" :class="{ 'input-error': errors.tipe_akuisisi }">
                  <option value="" disabled selected>-- Pilih Tipe --</option>
                  <option value="Custom-Made">Custom-Made</option>
                  <option value="Off-The-Shelf">Off-The-Shelf</option>
                </select>
                <small v-if="errors.tipe_akuisisi" class="error-message">{{ errors.tipe_akuisisi }}</small>
              </div>
            </div>
          </div>

          <!-- STEP 3: Upload Formulir Pengajuan (hanya untuk new app) -->
          <div v-else-if="step === 3" class="wizard-step" key="step3">
            <div class="step3-header">
              <div class="step3-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                  <line x1="12" y1="11" x2="12" y2="17"/>
                  <line x1="9" y1="14" x2="15" y2="14"/>
                </svg>
              </div>
              <div>
                <h4 class="step3-title">Upload Formulir Pengajuan</h4>
                <p class="step3-desc">Unggah formulir pengajuan resmi (PDF / DOC). Dokumen ini <strong>wajib diunggah</strong> sebagai syarat pengajuan aplikasi baru.</p>
              </div>
            </div>

            <!-- Template download -->
            <div class="step3-template-row">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              <a
                href="/templates/P22-Formulir-Usulan-Pengembangan-Aplikasi.pdf"
                class="step3-template-link"
                target="_blank"
                rel="noopener"
              >Buka template formulir pengajuan</a>
            </div>

            <!-- File picker -->
            <div class="step3-upload-area" :class="{ 'has-file': !!formulirFile }">
              <template v-if="!formulirFile">
                <label class="step3-upload-label" for="formulir-input">
                  <svg class="step3-upload-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                  </svg>
                  <span class="step3-upload-text">Klik untuk memilih file</span>
                  <span class="step3-upload-hint">PDF, DOC, atau DOCX - maks. 10 MB</span>
                  <input
                    id="formulir-input"
                    ref="formulirInput"
                    type="file"
                    accept=".pdf,.doc,.docx"
                    class="step3-file-input"
                    @change="onFormuliFileChange"
                  />
                </label>
              </template>
              <template v-else>
                <div class="step3-file-preview">
                  <div class="step3-file-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    </svg>
                  </div>
                  <div class="step3-file-info">
                    <span class="step3-file-name">{{ formulirFile.name }}</span>
                    <span class="step3-file-size">{{ (formulirFile.size / 1024).toFixed(1) }} KB</span>
                  </div>
                  <button type="button" class="step3-remove-btn" @click="removeFormuliFile" title="Hapus file">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  </button>
                </div>
              </template>
            </div>

            <p class="step3-required-note">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Formulir pengajuan wajib diunggah sebelum pengajuan dapat diproses oleh Pengelola Aplikasi.
            </p>
          </div>
        </transition>

        <!-- Navigation Buttons -->
        <div class="modal-actions">
          <!-- Step 1 -->
          <template v-if="step === 1">
            <button type="button" class="btn btn-cancel" @click="close">Batal</button>
            <button type="button" class="btn btn-submit" @click="nextStep">Lanjut &rarr;</button>
          </template>
          <!-- Step 2 -->
          <template v-else-if="step === 2">
            <button type="button" class="btn btn-cancel" :disabled="loading" @click="step = 1">&larr; Kembali</button>
            <!-- Edit mode: langsung Simpan. New mode: lanjut ke step 3 -->
            <button v-if="form.id" type="button" class="btn btn-submit" :disabled="loading" @click="nextStep2">
              <span v-if="loading">Menyimpan...</span>
              <span v-else>Simpan</span>
            </button>
            <button v-else type="button" class="btn btn-submit" :disabled="loading" @click="nextStep2">
              Lanjut &rarr;
            </button>
          </template>
          <!-- Step 3 (new app only) -->
          <template v-else-if="step === 3">
            <button type="button" class="btn btn-cancel" :disabled="loading || uploadingDoc" @click="step = 2">&larr; Kembali</button>
            <button type="button" class="btn btn-submit" :disabled="loading || uploadingDoc || !formulirFile" @click="handleSubmit">
              <span v-if="loading || uploadingDoc">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin spin-inline"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                {{ uploadingDoc ? 'Mengunggah...' : 'Mengajukan...' }}
              </span>
              <span v-else>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="submit-icon"><polyline points="20 6 9 17 4 12"/></svg>
                Ajukan
              </span>
            </button>
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
  </div>
</template>

<style scoped>
.app-form-modal {
  width: min(760px, calc(100vw - 32px));
  max-width: 760px;
  max-height: calc(100vh - 32px);
  overflow-y: auto;
}

.required-mark {
  color: var(--notion-red);
  font-weight: 800;
}

.field-help {
  display: block;
  margin-top: 4px;
  color: var(--notion-text-secondary);
  font-size: 12px;
  line-height: 1.45;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 24px;
}

.modal-actions .btn {
  margin-top: 0;
  flex: 1;
  justify-content: center;
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
  color: var(--notion-text-tertiary);
  transition: all 0.3s ease;
}

.step-item.active { color: var(--notion-text); }
.step-item.completed { color: #10b981; }

.step-number {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--notion-bg-secondary);
  border: 1px solid var(--notion-border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.step-item.active .step-number {
  background: var(--notion-blue);
  color: white;
  border-color: var(--notion-blue);
}

.step-item.completed .step-number {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.step-label { font-size: 13px; font-weight: 600; }

.step-line {
  flex: 1;
  height: 2px;
  background: var(--notion-border);
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
  color: var(--notion-text);
  border: 1px solid var(--notion-border);
}

.btn-cancel:hover:not(:disabled) {
  background: rgba(55, 53, 47, 0.13);
}

.btn-skip {
  flex: 1;
  background: transparent;
  color: var(--notion-text-secondary);
  border: 1px solid var(--notion-border);
  border-radius: 6px;
  padding: 10px 16px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-skip:hover:not(:disabled) {
  background: var(--notion-hover);
  color: var(--notion-text);
}
.btn-skip:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-submit {
  flex: 1;
}

.form-group-wide {
  grid-column: 1 / -1;
}

.input-error {
  border-color: var(--notion-red) !important;
  background-color: var(--notion-red-bg) !important;
}

.input-disabled {
  background-color: var(--notion-muted-surface) !important;
  cursor: not-allowed !important;
  color: var(--notion-text-secondary);
}

.char-counter-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  min-height: 18px;
  margin-top: 4px;
}

.char-counter {
  font-size: 11px;
  color: var(--notion-text-tertiary);
  margin-left: auto;
  transition: color 0.2s;
}

.char-counter--warn {
  color: #c05c00;
  font-weight: 600;
}

.error-message {
  display: block;
  color: var(--notion-red);
  font-size: 12px;
  margin-top: 4px;
  font-weight: 500;
}

.modal-footer {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid var(--notion-border);
}

.timestamp-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.timestamp-item {
  display: flex;
  gap: 8px;
  font-size: 12px;
  line-height: 1.5;
}

.timestamp-label {
  color: var(--notion-text-secondary);
  font-weight: 500;
  min-width: 70px;
}

.timestamp-value {
  color: var(--notion-text);
  cursor: default;
  border-bottom: 1px dotted rgba(55, 53, 47, 0.3);
  transition: all 0.15s ease;
}

.timestamp-value:hover {
  color: var(--notion-blue);
  border-bottom-color: var(--notion-blue);
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
  background: var(--notion-blue-bg, #eff6ff);
  border: 1px solid rgba(59, 130, 246, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--notion-blue, #2563eb);
}

.step3-title {
  margin: 0 0 6px;
  font-size: 15px;
  font-weight: 700;
  color: var(--notion-text);
}

.step3-desc {
  margin: 0;
  font-size: 13px;
  color: var(--notion-text-secondary);
  line-height: 1.6;
}

.step3-template-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 16px;
  color: var(--notion-text-secondary);
}

.step3-template-link {
  font-size: 13px;
  color: var(--notion-blue);
  text-decoration: none;
  font-weight: 500;
}
.step3-template-link:hover { text-decoration: underline; }

.step3-upload-area {
  border: 2px dashed var(--notion-border);
  border-radius: 10px;
  background: var(--notion-bg);
  transition: border-color 0.2s, background 0.2s;
  margin-bottom: 14px;
}

.step3-upload-area:hover {
  border-color: var(--notion-blue);
  background: var(--notion-blue-bg, #eff6ff);
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
  font-size: 14px;
  font-weight: 600;
  color: var(--notion-text);
}

.step3-upload-hint {
  font-size: 12px;
  color: var(--notion-text-secondary);
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
  font-size: 14px;
  font-weight: 600;
  color: var(--notion-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.step3-file-size {
  display: block;
  font-size: 12px;
  color: var(--notion-text-secondary);
  margin-top: 2px;
}

.step3-remove-btn {
  flex-shrink: 0;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 4px;
  cursor: pointer;
  color: var(--notion-text-secondary);
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
  font-size: 12px;
  color: #b45309;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 6px;
  padding: 8px 12px;
  margin: 0;
  line-height: 1.5;
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
  .app-form-modal {
    width: min(100vw - 20px, 760px);
    max-height: calc(100vh - 20px);
  }

  .wizard-stepper {
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 0;
    margin-bottom: 20px;
  }

  .step-item {
    flex: 1;
    min-width: 0;
    flex-direction: column;
    gap: 6px;
    text-align: center;
  }

  .step-label {
    font-size: 11px;
    line-height: 1.25;
  }

  .step-line {
    flex: 0 0 22px;
    margin: 14px -2px 0;
  }

  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
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

  .btn-cancel,
  .btn-submit {
    width: 100%;
    justify-content: center;
  }
}
</style>
