<script setup>
import { ref, watch } from 'vue'
import http from '../lib/http'
import { useToastStore } from '../stores/toast'
import Icons from './Icons.vue'
import { warnDev } from '../utils/logger'

const props = defineProps({
  show: { type: Boolean, default: false },
  appName: { type: String, default: '' },
  aplikasiId: { type: Number, default: null },
  aplikasi: { type: Object, default: null },  // Full aplikasi object to avoid N+1 query
  readOnly: { type: Boolean, default: false },
  // Fokuskan modal ke satu section saja: 'ui' | 'interop' | 'storage' | 'aktor' | 'transaksi' | null
  focusSection: { type: String, default: null },
  // Mode edit: 'full' (default), 'removeOnly' (tanpa tombol tambah), 'addOnly' (khusus transaksi)
  mode: { type: String, default: 'full' },
  // Sembunyikan seluruh section Transaksi (mis. saat dibuka dari Aksi)
  hideTransaksi: { type: Boolean, default: false }
})
const emit = defineEmits(['close', 'saved'])

const toast = useToastStore()

const loading = ref(false)
const aplikasiId = ref(null)
const aplikasiData = ref(null)

// Data arrays
const uiPlatforms = ref([]) // Array of selected platforms: ['dws', 'layanan']
const interops = ref([])
const storages = ref([])
const aktors = ref([])
const transaksis = ref([])

// File upload state
const selectedFile = ref(null)

// Available options
const availablePlatforms = ['dws', 'layanan']
const availableStorageTypes = ['db', 'object-storage']

const uiPlatformLabelMap = {
  dws: 'DWS',
  layanan: 'Layanan',
}

function formatUiPlatformLabel(value) {
  if (!value) return ''
  return uiPlatformLabelMap[value] || value
}

// Temporary refs for adding new items
const newPlatform = ref('')
const newInterop = ref('')
const newStorage = ref('')
const newAktor = ref('')

// Available aktor options
const availableAktors = ['Pengelola', 'User', 'Pegawai']

watch(() => props.show, async (newVal) => {
  if (newVal && (props.aplikasi || props.aplikasiId || props.appName)) {
    await loadData()
  }
}, { immediate: true })

async function loadData() {
  if (!props.aplikasiId && !props.appName) return
  loading.value = true
  try {
    // Best: Use passed aplikasi object (no extra query!)
    if (props.aplikasi) {
      aplikasiId.value = props.aplikasi.id
      aplikasiData.value = props.aplikasi
    }
    // Good: Use passed aplikasiId
    else if (props.aplikasiId) {
      aplikasiId.value = props.aplikasiId
      // Only fetch if aplikasi object not provided
      const appResponse = await http.get(`/aplikasi/${aplikasiId.value}`)
      aplikasiData.value = appResponse.data.data || appResponse.data
    }
    // Fallback: Get aplikasi by name (slowest)
    else {
      const aplikasiResponse = await http.get('/aplikasi')
      const aplikasi = aplikasiResponse.data.data.find(app => app.nama_aplikasi === props.appName)
      
      if (!aplikasi) {
        toast.push('Aplikasi tidak ditemukan', 'error')
        return
      }
      
      aplikasiId.value = aplikasi.id
      aplikasiData.value = aplikasi
    }
    
    // Get analisa desain for this aplikasi
    const analisaResponse = await http.get(`/analisa-desain?aplikasi_id=${aplikasiId.value}`)
    const analisas = analisaResponse.data.data || analisaResponse.data || []
    
    // Group data
    // UI Platform: Get unique values
    const uniquePlatforms = [...new Set(analisas.filter(a => a.ui_platform).map(a => a.ui_platform))]
    uiPlatforms.value = uniquePlatforms
    
    // Interoperabilitas: Get unique values
    const uniqueInterops = [...new Set(analisas.filter(a => a.interop_type && a.interop_type.trim()).map(a => a.interop_type.trim()))]
    interops.value = uniqueInterops
    
    // Storage: Get unique values
    const uniqueStorages = [...new Set(analisas.filter(a => a.storage_type && a.storage_type.trim()).map(a => a.storage_type.trim()))]
    storages.value = uniqueStorages
    
    // Aktor: Get unique values
    const uniqueAktors = [...new Set(analisas.filter(a => a.nama_aktor && a.nama_aktor.trim()).map(a => a.nama_aktor.trim()))]
    aktors.value = uniqueAktors
    
    transaksis.value = analisas.filter(a => a.method && a.url).map(a => ({
      method: a.method,
      url: a.url,
      tipe_resource: a.tipe_resource || 'terbuka',
      aktor_transaksi: a.aktor_transaksi || ''
    }))
    
    // Initialize empty transaksi row only for edit mode
    if (transaksis.value.length === 0 && !props.readOnly && !props.hideTransaksi) {
      transaksis.value = [{
        method: 'GET',
        url: '',
        tipe_resource: 'terbuka',
        aktor_transaksi: ''
      }]
    }
  } catch (error) {
    warnDev('[AnalisaDesainModal] loadData error:', error)
    toast.push('Gagal memuat data analisa desain', 'error')
  } finally {
    loading.value = false
  }
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file) {
    selectedFile.value = file
  } else {
    selectedFile.value = null
  }
}

function removePlatform(platform) {
  const index = uiPlatforms.value.indexOf(platform)
  if (index > -1) {
    uiPlatforms.value.splice(index, 1)
  }
}

function addPlatform(platform) {
  if (!uiPlatforms.value.includes(platform)) {
    uiPlatforms.value.push(platform)
  }
}

function handleAddPlatform() {
  if (newPlatform.value) {
    addPlatform(newPlatform.value)
    newPlatform.value = ''
  }
}

function addInterop() {
  if (newInterop.value && newInterop.value.trim()) {
    const value = newInterop.value.trim()
    if (!interops.value.includes(value)) {
      interops.value.push(value)
      newInterop.value = ''
    }
  }
}

function removeInterop(value) {
  const index = interops.value.indexOf(value)
  if (index > -1) {
    interops.value.splice(index, 1)
  }
}

function addStorage(storage) {
  if (storage && !storages.value.includes(storage)) {
    storages.value.push(storage)
  }
}

function handleAddStorage() {
  if (newStorage.value) {
    addStorage(newStorage.value)
    newStorage.value = ''
  }
}

function removeStorage(storage) {
  const index = storages.value.indexOf(storage)
  if (index > -1) {
    storages.value.splice(index, 1)
  }
}

function addAktor(aktor) {
  if (aktor && aktor.trim() && !aktors.value.includes(aktor.trim())) {
    aktors.value.push(aktor.trim())
  }
}

function removeAktor(aktor) {
  const index = aktors.value.indexOf(aktor)
  if (index > -1) {
    aktors.value.splice(index, 1)
  }
}

function handleAddAktor() {
  if (newAktor.value) {
    addAktor(newAktor.value)
    newAktor.value = ''
  }
}

function addTransaksi() {
  transaksis.value.push({
    method: 'GET',
    url: '',
    tipe_resource: 'terbuka',
    aktor_transaksi: ''
  })
}

function removeTransaksi(index) {
  transaksis.value.splice(index, 1)
}

async function handleSubmit() {
  if (!aplikasiId.value) return
  
  loading.value = true
  try {
    // Build all items to be saved
    const items = []
    
    // UI Platforms
    uiPlatforms.value.forEach(platform => {
      if (platform) {
        items.push({ ui_platform: platform })
      }
    })
    
    // Interops
    interops.value.forEach(interop => {
      if (interop && interop.trim()) {
        items.push({ interop_type: interop.trim() })
      }
    })
    
    // Storages
    storages.value.forEach(storage => {
      if (storage && storage.trim()) {
        items.push({ storage_type: storage.trim() })
      }
    })
    
    // Aktors
    aktors.value.forEach(aktor => {
      if (aktor && aktor.trim()) {
        items.push({ nama_aktor: aktor.trim() })
      }
    })
    
    // Transaksis
    transaksis.value.forEach(transaksi => {
      if (transaksi.method && transaksi.url && transaksi.url.trim()) {
        items.push({
          method: transaksi.method,
          url: transaksi.url.trim(),
          tipe_resource: transaksi.tipe_resource,
          aktor_transaksi: transaksi.aktor_transaksi || ''
        })
      }
    })
    
    // Single batch update request (much faster!)
    await http.put(`/analisa-desain/batch/${aplikasiId.value}`, { items })
    
    // Document upload
    if (selectedFile.value) {
      const formData = new FormData()
      formData.append('document_type', 'laporan_analisa_desain')
      formData.append('file', selectedFile.value)
      await http.post(`/aplikasi/${aplikasiId.value}/documents`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    }
    
    toast.push('Data Analisa Desain berhasil disimpan!', 'success')
    emit('saved')
    emit('close')
  } catch (error) {
    warnDev('[AnalisaDesainModal] save error:', error)
    toast.push('Gagal menyimpan data', 'error')
  } finally {
    loading.value = false
  }
}

function close() {
  emit('close')
}
</script>

<template>
  <div v-if="show" class="modal active" role="dialog" aria-modal="true" :aria-labelledby="`modal-title-analisa`">
    <div class="modal-content modal-content-scrollable analisa-modal">
      <div class="modal-header modal-header-sticky">
        <h3 :id="`modal-title-analisa`">{{ readOnly ? 'Lihat' : 'Edit' }} Analisa Desain - {{ appName }}</h3>
        <button class="close-btn" @click="close" aria-label="Tutup modal">&times;</button>
      </div>
      
      <div class="modal-body">
        <div v-if="loading" class="loading">Memuat data...</div>
      
      <form v-else class="analisa-design-form" @submit.prevent="handleSubmit">
        <!-- UI Platform Section -->
        <div v-if="!props.focusSection || props.focusSection==='ui'" class="detail-section">
          <h4>UI Platform</h4>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="uiPlatforms.length > 0" class="platform-badges">
              <span v-for="(platform, idx) in uiPlatforms" :key="idx" class="badge badge-info">
                {{ formatUiPlatformLabel(platform) }}
              </span>
            </div>
            <p v-else class="empty-text">
              Belum ada data UI Platform.
            </p>
          </div>
          
          <!-- Edit mode: Show as removable badges -->
          <div v-else class="badge-section-edit">
            <!-- Selected platforms with X button -->
            <div v-if="uiPlatforms.length > 0" class="platform-badges-editable">
              <span v-for="platform in uiPlatforms" :key="platform" class="badge badge-info badge-removable">
                {{ formatUiPlatformLabel(platform) }}
                <button type="button" class="badge-remove-btn" @click="removePlatform(platform)" title="Hapus">
                  <Icons name="x" :size="12" />
                </button>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada UI Platform yang dipilih</p>
            
            <!-- Add new platform dropdown -->
            <div v-if="props.mode !== 'removeOnly'" class="platform-add-group">
              <select v-model="newPlatform" @change="handleAddPlatform" class="platform-select">
                <option value="" disabled>+ Tambah UI Platform</option>
                <option v-for="platform in availablePlatforms" :key="platform" :value="platform" :disabled="uiPlatforms.includes(platform)">
                  {{ formatUiPlatformLabel(platform) }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Interop Section -->
        <div v-if="!props.focusSection || props.focusSection==='interop'" class="detail-section">
          <h4>Interoperabilitas</h4>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="interops.length > 0 && interops[0]" class="platform-badges">
              <span v-for="(interop, idx) in interops" :key="idx" class="badge badge-success">
                {{ interop }}
              </span>
            </div>
            <p v-else class="empty-text">
              Belum ada data interoperabilitas.
            </p>
          </div>
          
          <!-- Edit mode: Show as removable badges -->
          <div v-else class="badge-section-edit">
            <!-- Selected interops with X button -->
            <div v-if="interops.length > 0" class="platform-badges-editable">
              <span v-for="interop in interops" :key="interop" class="badge badge-success badge-removable">
                {{ interop }}
                <button type="button" class="badge-remove-btn" @click="removeInterop(interop)" title="Hapus">
                  <Icons name="x" :size="12" />
                </button>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada interoperabilitas</p>
            
            <!-- Add new interop input -->
            <div v-if="props.mode !== 'removeOnly'" class="interop-add-group">
              <input 
                type="text" 
                v-model="newInterop" 
                @keyup.enter="addInterop"
                placeholder="Ketik nama interoperabilitas..."
                maxlength="100"
                aria-label="Tambah interoperabilitas"
                class="interop-input"
              />
              <button type="button" class="btn-add-small" @click="addInterop" :disabled="!newInterop || !newInterop.trim()">
                <Icons name="plus" :size="14" />
              </button>
            </div>
          </div>
        </div>

        <!-- Storage Section -->
        <div v-if="!props.focusSection || props.focusSection==='storage'" class="detail-section">
          <h4>Storage</h4>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="storages.length > 0 && storages[0]" class="platform-badges">
              <span v-for="(storage, idx) in storages" :key="idx" class="badge badge-warning">
                {{ storage === 'db' ? 'Database' : storage === 'object-storage' ? 'Object Storage' : storage === 'cache' ? 'Cache' : storage }}
              </span>
            </div>
            <p v-else class="empty-text">
              Belum ada data storage.
            </p>
          </div>
          
          <!-- Edit mode: Show as removable badges -->
          <div v-else class="badge-section-edit">
            <!-- Selected storages with X button -->
            <div v-if="storages.length > 0" class="platform-badges-editable">
              <span v-for="storage in storages" :key="storage" class="badge badge-warning badge-removable">
                {{ storage === 'db' ? 'Database' : storage === 'object-storage' ? 'Object Storage' : storage === 'cache' ? 'Cache' : storage }}
                <button type="button" class="badge-remove-btn" @click="removeStorage(storage)" title="Hapus">
                  <Icons name="x" :size="12" />
                </button>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada storage yang dipilih</p>
            
            <!-- Add new storage dropdown -->
            <div v-if="props.mode !== 'removeOnly'" class="platform-add-group">
              <select v-model="newStorage" @change="handleAddStorage" class="platform-select">
                <option value="" disabled>+ Tambah Storage</option>
                <option v-for="storageType in availableStorageTypes" :key="storageType" :value="storageType" :disabled="storages.includes(storageType)">
                  {{ storageType === 'db' ? 'Database' : storageType === 'object-storage' ? 'Object Storage' : storageType === 'cache' ? 'Cache' : storageType }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Aktor Section -->
        <div v-if="!props.focusSection || props.focusSection==='aktor'" class="detail-section">
          <h4>Aktor</h4>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="aktors.length > 0" class="platform-badges">
              <span v-for="(aktor, idx) in aktors" :key="idx" class="badge badge-info">
                {{ aktor }}
              </span>
            </div>
            <p v-else class="empty-text">
              Belum ada data aktor.
            </p>
          </div>
          
          <!-- Edit mode: Show as removable badges -->
          <div v-else class="badge-section-edit">
            <!-- Selected aktors with X button -->
            <div v-if="aktors.length > 0" class="platform-badges-editable">
              <span v-for="aktor in aktors" :key="aktor" class="badge badge-info badge-removable">
                {{ aktor }}
                <button type="button" class="badge-remove-btn" @click="removeAktor(aktor)" title="Hapus">
                  <Icons name="x" :size="12" />
                </button>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada aktor yang dipilih</p>
            
            <!-- Add new aktor dropdown -->
            <div v-if="props.mode !== 'removeOnly'" class="platform-add-group">
              <select v-model="newAktor" @change="handleAddAktor" class="platform-select" aria-label="Pilih aktor">
                <option value="" disabled>+ Tambah Aktor</option>
                <option v-for="aktorOption in availableAktors" :key="aktorOption" :value="aktorOption" :disabled="aktors.includes(aktorOption)">
                  {{ aktorOption }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Transaksi Section -->
        <div v-if="(!props.focusSection || props.focusSection==='transaksi') && !props.hideTransaksi" class="detail-section">
          <h4>Transaksi</h4>
          
          <!-- Read-only mode: Show as cards -->
          <div v-if="readOnly">
            <div v-if="transaksis.length > 0">
              <div v-for="(transaksi, idx) in transaksis" :key="idx" class="transaksi-item-readonly">
                <div class="transaksi-row">
                  <span :class="'badge badge-' + transaksi.method.toLowerCase()">{{ transaksi.method }}</span>
                  <code class="transaksi-url">{{ transaksi.url }}</code>
                </div>
                <div class="transaksi-meta">
                  <span class="meta-item">
                    <strong>Tipe:</strong> {{ transaksi.tipe_resource || '-' }}
                  </span>
                  <span class="meta-item">
                    <strong>Aktor:</strong> {{ transaksi.aktor_transaksi || '-' }}
                  </span>
                </div>
              </div>
            </div>
            <p v-else class="empty-text">
              Belum ada data transaksi.
            </p>
          </div>
          
          <!-- Edit mode: Show as form -->
          <div v-else>
            <div v-for="(transaksi, idx) in transaksis" :key="idx" class="transaksi-item">
              <div class="transaksi-header">
                <span class="transaksi-label">Transaksi {{ idx + 1 }}</span>
                <button
                  v-if="props.mode !== 'addOnly'"
                  type="button"
                  class="btn-icon-delete"
                  @click="removeTransaksi(idx)"
                  title="Hapus Transaksi"
                >
                  <Icons name="trash" :size="16" />
                </button>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Method</label>
                  <select v-model="transaksi.method">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>URL</label>
                  <input 
                    type="text" 
                    v-model="transaksi.url" 
                    placeholder="/api/..." 
                    maxlength="255"
                    pattern="^/.*"
                    title="URL harus dimulai dengan garis miring (/)"
                  />
                  <small v-if="transaksi.url && !transaksi.url.startsWith('/')" class="form-hint error">
                    URL harus dimulai dengan / (garis miring)
                  </small>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Tipe Resource</label>
                  <select v-model="transaksi.tipe_resource">
                    <option value="terbuka">Terbuka</option>
                    <option value="tertutup">Tertutup</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Aktor</label>
                  <select v-model="transaksi.aktor_transaksi">
                    <option value="" disabled selected>-- Pilih Aktor --</option>
                    <option value="Pengelola">Pengelola</option>
                    <option value="User">User</option>
                    <option value="Pegawai">Pegawai</option>
                  </select>
                </div>
              </div>
            </div>
            <button v-if="(!props.focusSection && props.mode==='full') || (props.focusSection==='transaksi' && props.mode!=='removeOnly')" type="button" class="btn-add-item" @click="addTransaksi">
              <Icons name="plus" :size="14" />
              Tambah Transaksi
            </button>
          </div>
        </div>

        <!-- Dokumen Laporan Section -->
        <div v-if="!readOnly" class="detail-section">
          <h4>Laporan Analisa Desain</h4>
          <div class="upload-container">
            <label class="custom-file-upload">
              <input type="file" accept=".pdf,.doc,.docx" @change="handleFileSelect" class="file-input-hidden" />
              <div class="upload-content">
                <Icons name="upload-cloud" :size="24" class="upload-icon" />
                <span class="upload-text">
                  {{ selectedFile ? selectedFile.name : 'Pilih file PDF atau DOC' }}
                </span>
                <span v-if="!selectedFile" class="upload-subtext">Maksimal ukuran file 10MB</span>
              </div>
            </label>
            <small class="form-hint">
              Unggah laporan hasil analisa desain yang telah selesai disusun sebagai kelengkapan.
            </small>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" @click="close" class="btn btn-secondary">{{ readOnly ? 'Tutup' : 'Batal' }}</button>
          <button v-if="!readOnly" type="submit" class="btn" :disabled="loading">{{ loading ? 'Menyimpan...' : 'Simpan Perubahan' }}</button>
        </div>
      </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.analisa-modal {
  width: min(1160px, calc(100vw - 48px));
  max-width: 1160px;
  max-height: calc(100vh - 48px);
  border-radius: 16px;
  overflow: hidden;
  background: var(--notion-bg);
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
}

.analisa-modal .modal-header {
  padding: 22px 28px;
  margin: 0;
  border-bottom: 1px solid var(--notion-border);
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(10px);
}

.analisa-modal .modal-header h3 {
  margin: 0;
  color: var(--notion-text);
  font-size: 20px;
  font-weight: 750;
  letter-spacing: 0;
  line-height: 1.35;
}

.analisa-modal .close-btn {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  border: 1px solid transparent;
  background: var(--notion-bg-secondary);
  color: var(--notion-text-secondary);
  font-size: 24px;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.analisa-modal .close-btn:hover {
  border-color: var(--notion-border);
  color: var(--notion-text);
  background: var(--notion-hover);
}

.analisa-modal .modal-body {
  padding: 24px 28px 28px;
}

.analisa-design-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.analisa-design-form .detail-section {
  margin: 0;
  padding: 0 0 20px;
  border-bottom: 1px solid var(--notion-border);
}

.analisa-design-form .detail-section:last-of-type {
  border-bottom: 0;
}

.analisa-design-form .detail-section h4 {
  margin: 0 0 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--notion-border);
  color: var(--notion-blue);
  font-size: 15px;
  font-weight: 750;
  line-height: 1.35;
}

.platform-badges,
.platform-badges-editable {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 34px;
  align-items: center;
}

.badge-section-edit {
  display: grid;
  gap: 12px;
}

.badge-removable {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 100%;
  padding: 6px 8px 6px 10px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 650;
  line-height: 1.2;
}

.badge-remove-btn {
  width: 18px;
  height: 18px;
  padding: 0;
  border: 0;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.08);
  color: currentColor;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex: 0 0 auto;
}

.badge-remove-btn:hover {
  background: rgba(15, 23, 42, 0.16);
}

.badge-empty-hint,
.empty-text {
  color: var(--notion-text-secondary);
  font-size: 13.5px;
  margin: 4px 0 0;
  font-style: italic;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  margin: 0;
  padding-top: 18px;
  gap: 10px;
  border-top: 1px solid var(--notion-border);
  position: sticky;
  bottom: 0;
  background: linear-gradient(180deg, rgba(255,255,255,0.9), #fff);
  z-index: 2;
}

.platform-add-group,
.interop-add-group {
  display: flex;
  gap: 10px;
  align-items: center;
}

.platform-select,
.interop-input,
.analisa-design-form input,
.analisa-design-form select {
  width: 100%;
  min-height: 42px;
  padding: 9px 12px;
  border: 1px solid var(--notion-border);
  border-radius: 8px;
  background: var(--notion-bg-secondary);
  color: var(--notion-text);
  font-size: 14px;
  line-height: 1.4;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.platform-select:focus,
.interop-input:focus,
.analisa-design-form input:focus,
.analisa-design-form select:focus {
  border-color: var(--notion-blue);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(31, 63, 147, 0.12);
}

.btn-add-small {
  width: 42px;
  height: 42px;
  border: 0;
  border-radius: 8px;
  background: var(--notion-blue);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  cursor: pointer;
}

.btn-add-small:disabled {
  cursor: not-allowed;
  background: #d0d5dd;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  color: var(--notion-text-secondary);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.transaksi-item,
.transaksi-item-readonly {
  padding: 16px;
  border: 1px solid var(--notion-border);
  border-radius: 10px;
  background: var(--notion-bg-secondary);
  margin-bottom: 12px;
}

.transaksi-header,
.transaksi-row,
.transaksi-meta {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.transaksi-header {
  justify-content: space-between;
  margin-bottom: 12px;
}

.transaksi-label {
  font-size: 13px;
  font-weight: 750;
  color: var(--notion-text);
}

.transaksi-url {
  color: var(--notion-text);
  background: #fff;
  border: 1px solid var(--notion-border);
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 12.5px;
  word-break: break-all;
}

.transaksi-meta {
  margin-top: 10px;
  color: var(--notion-text-secondary);
  font-size: 13px;
}

.btn-icon-delete {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--notion-border);
  border-radius: 8px;
  background: #fff;
  color: var(--notion-red);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.btn-icon-delete:hover {
  background: #fef2f2;
  border-color: #fecaca;
}

.btn-add-item {
  min-height: 40px;
  border: 1px dashed var(--notion-border);
  border-radius: 8px;
  background: var(--notion-bg-secondary);
  color: var(--notion-blue);
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 14px;
  cursor: pointer;
}

.input-error {
  border-color: var(--notion-red);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.field-error {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--notion-red);
}

.form-hint {
  margin-top: 12px;
  font-size: 12px;
}

.form-hint.error {
  color: var(--notion-red);
}

.upload-container {
  margin-top: 12px;
}

.custom-file-upload {
  display: block;
  border: 1px dashed var(--notion-border);
  border-radius: 10px;
  padding: 24px;
  text-align: center;
  cursor: pointer;
  background-color: var(--notion-bg-secondary);
  transition: all 0.2s ease;
}

.custom-file-upload:hover {
  border-color: var(--notion-blue);
  background-color: rgba(35, 131, 226, 0.05);
}

.file-input-hidden {
  display: none;
}

.upload-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.upload-icon {
  color: var(--notion-text-secondary);
}

.custom-file-upload:hover .upload-icon {
  color: var(--notion-blue);
}

.upload-text {
  font-size: 14px;
  font-weight: 500;
  color: var(--notion-text-primary);
}

.upload-subtext {
  font-size: 12px;
  color: var(--notion-text-secondary);
}

@media (max-width: 768px) {
  .analisa-modal {
    width: calc(100vw - 24px);
    max-height: calc(100vh - 24px);
  }

  .analisa-modal .modal-header,
  .analisa-modal .modal-body {
    padding-left: 18px;
    padding-right: 18px;
  }

  .form-row,
  .modal-actions {
    grid-template-columns: 1fr;
  }

  .modal-actions {
    flex-direction: column-reverse;
  }

  .modal-actions .btn {
    width: 100%;
  }
}
</style>

