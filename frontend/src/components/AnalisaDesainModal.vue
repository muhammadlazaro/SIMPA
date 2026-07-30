<script setup>
import {
  Badge,
  Button,
  Modal,
  SingleFileUpload,
  Skeleton,
  TextField,
  Tooltip,
} from '@idds/vue'
import { IconDeviceFloppy, IconPlus, IconTrash, IconX } from '@tabler/icons-vue'
import { ref, watch } from 'vue'
import http from '../lib/http'
import { useToastStore } from '../stores/toast'
import Icons from './Icons.vue'
import IddsSelect from './IddsSelect.vue'
import { resolveIddsFileSelection } from '../utils/fileUpload'
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
const resolvedAplikasiId = ref(null)
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
const platformOptions = availablePlatforms.map((value) => ({
  label: value === 'dws' ? 'DWS' : 'Layanan',
  value,
}))
const storageOptions = availableStorageTypes.map((value) => ({
  label: value === 'db' ? 'Database' : 'Object Storage',
  value,
}))
const methodOptions = ['GET', 'POST', 'PUT', 'DELETE'].map((value) => ({ label: value, value }))
const resourceOptions = [
  { label: 'Terbuka', value: 'terbuka' },
  { label: 'Tertutup', value: 'tertutup' },
]

const uiPlatformLabelMap = {
  dws: 'DWS',
  layanan: 'Layanan',
}

const storageLabelMap = {
  db: 'Database',
  'object-storage': 'Object Storage',
  cache: 'Cache',
}

function formatUiPlatformLabel(value) {
  if (!value) return ''
  return uiPlatformLabelMap[value] || value
}

function formatStorageLabel(value) {
  if (!value) return ''
  return storageLabelMap[value] || value
}

function getSectionCount(key) {
  const counters = {
    ui: uiPlatforms.value.length,
    interop: interops.value.filter(Boolean).length,
    storage: storages.value.filter(Boolean).length,
    aktor: aktors.value.filter(Boolean).length,
    transaksi: transaksis.value.filter((item) => item?.method && item?.url?.trim()).length,
    dokumen: selectedFile.value ? 1 : 0,
  }

  return counters[key] || 0
}

function getSectionStatusText(key) {
  const count = getSectionCount(key)
  if (key === 'dokumen') return selectedFile.value ? 'Dipilih' : 'Opsional'
  if (count === 0) return 'Belum diisi'
  return `${count} item`
}

// Temporary refs for adding new items
const newPlatform = ref('')
const newInterop = ref('')
const newStorage = ref('')
const newAktor = ref('')

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
      resolvedAplikasiId.value = props.aplikasi.id
      aplikasiData.value = props.aplikasi
    }
    // Good: Use passed aplikasiId
    else if (props.aplikasiId) {
      resolvedAplikasiId.value = props.aplikasiId
      // Only fetch if aplikasi object not provided
      const appResponse = await http.get(`/aplikasi/${resolvedAplikasiId.value}`)
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
      
      resolvedAplikasiId.value = aplikasi.id
      aplikasiData.value = aplikasi
    }
    
    // Get analisa desain for this aplikasi
    const analisaResponse = await http.get(`/analisa-desain?aplikasi_id=${resolvedAplikasiId.value}`)
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
    toast.push('Gagal memuat data analisis desain', 'error')
  } finally {
    loading.value = false
  }
}

function handleFileSelect(file, validation) {
  const selection = resolveIddsFileSelection(file, validation)
  selectedFile.value = selection.file

  if (selection.error) {
    toast.push(selection.error, 'error')
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
  if (!newPlatform.value) return
  addPlatform(newPlatform.value)
  newPlatform.value = ''
}

function selectPlatform(value) {
  newPlatform.value = value
  handleAddPlatform()
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
  if (!newStorage.value) return
  addStorage(newStorage.value)
  newStorage.value = ''
}

function selectStorage(value) {
  newStorage.value = value
  handleAddStorage()
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
  if (!resolvedAplikasiId.value) return
  
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
    await http.put(`/analisa-desain/batch/${resolvedAplikasiId.value}`, { items })
    
    // Document upload
    if (selectedFile.value) {
      const formData = new FormData()
      formData.append('document_type', 'laporan_analisa_desain')
      formData.append('file', selectedFile.value)
      await http.post(`/aplikasi/${resolvedAplikasiId.value}/documents`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
    }
    
    toast.push('Data Analisis Desain berhasil disimpan!', 'success')
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
  <Modal
    :model-value="show"
    :title="readOnly ? 'Detail analisis desain' : 'Edit analisis desain'"
    :description="appName || aplikasiData?.nama_aplikasi || 'Aplikasi'"
    size="xl"
    :show-close-button="true"
    :show-footer="false"
    close-label="Tutup analisis desain"
    :close-on-backdrop="!loading"
    :close-on-escape="!loading"
    :persistent="loading"
    padding-body="0"
    @update:model-value="($event) => { if (!$event) close() }"
  >
    <div class="modal-body analisa-modal-body">
        <div v-if="loading" class="analisa-loading">
          <Skeleton v-for="index in 4" :key="index" height="96px" width="100%" rounded="lg" />
          <span class="sr-only">Memuat data analisis desain</span>
        </div>
      
      <form v-else class="analisa-design-form" @submit.prevent="handleSubmit">
        <div class="analisa-form-panels">
        <!-- UI Platform Section -->
        <section v-if="!props.focusSection || props.focusSection==='ui'" id="analisa-section-ui" class="detail-section analysis-editor-card">
          <div class="analysis-section-header">
            <div class="analysis-section-title">
              <span class="analysis-section-icon">
                <Icons name="monitor" :size="18" />
              </span>
              <div>
                <h4>UI Platform</h4>
                <p>Kanal utama antarmuka aplikasi yang digunakan oleh pengguna.</p>
              </div>
            </div>
            <span class="analysis-section-count">{{ getSectionStatusText('ui') }}</span>
          </div>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="uiPlatforms.length > 0" class="platform-badges">
              <Badge v-for="(platform, idx) in uiPlatforms" :key="idx" type="soft" variant="info" size="md">
                {{ formatUiPlatformLabel(platform) }}
              </Badge>
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
                <Tooltip :title="`Hapus ${formatUiPlatformLabel(platform)}`" placement="top">
                  <Button hierarchy="custom" size="sm" class="badge-remove-btn" :prefix-icon="IconX" :aria-label="`Hapus ${formatUiPlatformLabel(platform)}`" @click="removePlatform(platform)" />
                </Tooltip>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada UI Platform yang dipilih</p>
            
            <!-- Add new platform dropdown -->
            <IddsSelect
              v-if="props.mode !== 'removeOnly'"
              :model-value="newPlatform"
              label="Tambah UI Platform"
              :options="platformOptions.map((option) => ({ ...option, disabled: uiPlatforms.includes(option.value) }))"
              placeholder="Pilih UI Platform"
              size="md"
              width="100%"
              panel-width="100%"
              @update:model-value="selectPlatform"
            />
          </div>
        </section>

        <!-- Interop Section -->
        <section v-if="!props.focusSection || props.focusSection==='interop'" id="analisa-section-interop" class="detail-section analysis-editor-card">
          <div class="analysis-section-header">
            <div class="analysis-section-title">
              <span class="analysis-section-icon">
                <Icons name="grid" :size="18" />
              </span>
              <div>
                <h4>Interoperabilitas</h4>
                <p>Catat sistem, layanan, atau integrasi yang berhubungan dengan aplikasi.</p>
              </div>
            </div>
            <span class="analysis-section-count">{{ getSectionStatusText('interop') }}</span>
          </div>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="interops.length > 0 && interops[0]" class="platform-badges">
              <Badge v-for="(interop, idx) in interops" :key="idx" type="soft" variant="success" size="md">
                {{ interop }}
              </Badge>
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
                <Tooltip :title="`Hapus ${interop}`" placement="top">
                  <Button hierarchy="custom" size="sm" class="badge-remove-btn" :prefix-icon="IconX" :aria-label="`Hapus ${interop}`" @click="removeInterop(interop)" />
                </Tooltip>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada interoperabilitas</p>
            
            <!-- Add new interop input -->
            <div v-if="props.mode !== 'removeOnly'" class="interop-add-group">
              <TextField
                v-model="newInterop"
                label="Tambah interoperabilitas"
                placeholder="Contoh: SSO Instansi"
                :max-length="100"
                size="md"
                @keyup.enter="addInterop"
              />
              <Tooltip title="Tambahkan interoperabilitas" placement="top">
                <Button
                  hierarchy="secondary"
                  size="lg"
                  :prefix-icon="IconPlus"
                  :disabled="!newInterop || !newInterop.trim()"
                  aria-label="Tambahkan interoperabilitas"
                  @click="addInterop"
                />
              </Tooltip>
            </div>
          </div>
        </section>

        <!-- Storage Section -->
        <section v-if="!props.focusSection || props.focusSection==='storage'" id="analisa-section-storage" class="detail-section analysis-editor-card">
          <div class="analysis-section-header">
            <div class="analysis-section-title">
              <span class="analysis-section-icon">
                <Icons name="server" :size="18" />
              </span>
              <div>
                <h4>Storage</h4>
                <p>Pilih jenis penyimpanan data, dokumen, atau objek aplikasi.</p>
              </div>
            </div>
            <span class="analysis-section-count">{{ getSectionStatusText('storage') }}</span>
          </div>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="storages.length > 0 && storages[0]" class="platform-badges">
              <Badge v-for="(storage, idx) in storages" :key="idx" type="soft" variant="warning" size="md">
                {{ formatStorageLabel(storage) }}
              </Badge>
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
                {{ formatStorageLabel(storage) }}
                <Tooltip :title="`Hapus ${formatStorageLabel(storage)}`" placement="top">
                  <Button hierarchy="custom" size="sm" class="badge-remove-btn" :prefix-icon="IconX" :aria-label="`Hapus ${formatStorageLabel(storage)}`" @click="removeStorage(storage)" />
                </Tooltip>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada storage yang dipilih</p>
            
            <!-- Add new storage dropdown -->
            <IddsSelect
              v-if="props.mode !== 'removeOnly'"
              :model-value="newStorage"
              label="Tambah storage"
              :options="storageOptions.map((option) => ({ ...option, disabled: storages.includes(option.value) }))"
              placeholder="Pilih jenis storage"
              size="md"
              width="100%"
              panel-width="100%"
              @update:model-value="selectStorage"
            />
          </div>
        </section>

        <!-- Aktor Section -->
        <section v-if="!props.focusSection || props.focusSection==='aktor'" id="analisa-section-aktor" class="detail-section analysis-editor-card">
          <div class="analysis-section-header">
            <div class="analysis-section-title">
              <span class="analysis-section-icon">
                <Icons name="user" :size="18" />
              </span>
              <div>
                <h4>Aktor</h4>
                <p>Daftarkan aktor atau peran yang menggunakan fitur aplikasi.</p>
              </div>
            </div>
            <span class="analysis-section-count">{{ getSectionStatusText('aktor') }}</span>
          </div>
          
          <!-- Read-only mode: Show as badges -->
          <div v-if="readOnly">
            <div v-if="aktors.length > 0" class="platform-badges">
              <Badge v-for="(aktor, idx) in aktors" :key="idx" type="soft" variant="info" size="md">
                {{ aktor }}
              </Badge>
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
                <Tooltip :title="`Hapus ${aktor}`" placement="top">
                  <Button hierarchy="custom" size="sm" class="badge-remove-btn" :prefix-icon="IconX" :aria-label="`Hapus ${aktor}`" @click="removeAktor(aktor)" />
                </Tooltip>
              </span>
            </div>
            <p v-else class="badge-empty-hint">Belum ada aktor yang dipilih</p>
            
            <!-- Add new aktor input -->
            <div v-if="props.mode !== 'removeOnly'" class="interop-add-group">
              <TextField
                v-model="newAktor"
                label="Tambah aktor"
                placeholder="Contoh: Operator Unit Kerja"
                :max-length="100"
                size="md"
              />
              <Tooltip title="Tambahkan aktor" placement="top">
                <Button
                  hierarchy="secondary"
                  size="lg"
                  :prefix-icon="IconPlus"
                  :disabled="!newAktor || !newAktor.trim()"
                  aria-label="Tambahkan aktor"
                  @click="handleAddAktor"
                />
              </Tooltip>
            </div>
          </div>
        </section>

        <!-- Transaksi Section -->
        <section v-if="(!props.focusSection || props.focusSection==='transaksi') && !props.hideTransaksi" id="analisa-section-transaksi" class="detail-section analysis-editor-card">
          <div class="analysis-section-header">
            <div class="analysis-section-title">
              <span class="analysis-section-icon">
                <Icons name="code" :size="18" />
              </span>
              <div>
                <h4>Transaksi</h4>
                <p>Kelola endpoint atau transaksi API penting yang menjadi bagian desain.</p>
              </div>
            </div>
            <span class="analysis-section-count">{{ getSectionStatusText('transaksi') }}</span>
          </div>
          
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
                <Tooltip v-if="props.mode !== 'addOnly'" title="Hapus transaksi" placement="top">
                  <Button
                    hierarchy="secondary"
                    size="sm"
                    :prefix-icon="IconTrash"
                    aria-label="Hapus transaksi"
                    @click="removeTransaksi(idx)"
                  />
                </Tooltip>
              </div>
              <div class="form-row">
                <IddsSelect
                  v-model="transaksi.method"
                  label="Method"
                  :options="methodOptions"
                  size="md"
                  width="100%"
                  panel-width="100%"
                />
                <TextField
                  v-model="transaksi.url"
                  label="URL endpoint"
                  placeholder="Contoh: /api/resource"
                  :max-length="255"
                  size="md"
                  :status="transaksi.url && !transaksi.url.startsWith('/') ? 'error' : 'neutral'"
                  :status-message="transaksi.url && !transaksi.url.startsWith('/') ? 'URL harus dimulai dengan garis miring (/).' : ''"
                />
              </div>
              <div class="form-row">
                <IddsSelect
                  v-model="transaksi.tipe_resource"
                  label="Tipe resource"
                  :options="resourceOptions"
                  size="md"
                  width="100%"
                  panel-width="100%"
                />
                <TextField
                  v-model="transaksi.aktor_transaksi"
                  label="Aktor"
                  placeholder="Contoh: Operator Unit Kerja"
                  :max-length="100"
                  size="md"
                />
              </div>
            </div>
            <Button
              v-if="(!props.focusSection && props.mode==='full') || (props.focusSection==='transaksi' && props.mode!=='removeOnly')"
              hierarchy="secondary"
              size="md"
              :prefix-icon="IconPlus"
              @click="addTransaksi"
            >
              Tambah transaksi
            </Button>
          </div>
        </section>

        <!-- Dokumen Laporan Section -->
        <section v-if="!readOnly" id="analisa-section-dokumen" class="detail-section analysis-editor-card">
          <div class="analysis-section-header">
            <div class="analysis-section-title">
              <span class="analysis-section-icon">
                <Icons name="file-text" :size="18" />
              </span>
              <div>
                <h4>Laporan Analisis Desain</h4>
                <p>Unggah dokumen akhir sebagai bukti kelengkapan analisis desain.</p>
              </div>
            </div>
            <span class="analysis-section-count">{{ getSectionStatusText('dokumen') }}</span>
          </div>
          <SingleFileUpload
            title="Pilih laporan analisis desain"
            description="PDF, DOC, atau DOCX; maksimal 8 MB."
            accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            :allowed-extensions="['pdf', 'doc', 'docx']"
            :max-size="8 * 1000 * 1000"
            :validate-magic-number="true"
            :disabled="loading"
            :status="selectedFile ? 'success' : 'idle'"
            @change="handleFileSelect"
            @remove="selectedFile = null"
          />
        </section>

        <div class="modal-actions">
          <Button hierarchy="secondary" size="lg" type="button" @click="close">
            {{ readOnly ? 'Tutup' : 'Batal' }}
          </Button>
          <Button v-if="!readOnly" hierarchy="primary" size="lg" type="submit" :prefix-icon="IconDeviceFloppy" :disabled="loading">
            {{ loading ? 'Menyimpan perubahan' : 'Simpan perubahan' }}
          </Button>
        </div>
        </div>
      </form>
      </div>
  </Modal>
</template>

<style scoped>
.analisa-modal {
  width: min(1180px, calc(100vw - 48px));
  max-width: 1160px;
  height: min(860px, calc(100vh - 48px));
  max-height: calc(100vh - 48px);
  padding: 0 !important;
  border-radius: 8px;
  overflow: hidden !important;
  display: flex;
  flex-direction: column;
  border: 1px solid #dbe3ef;
  background: #f7f9fc;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
}

.analisa-modal .modal-header {
  padding: 22px 28px;
  margin: 0;
  border-bottom: 1px solid var(--ina-stroke-primary);
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(10px);
}

.analisa-modal .modal-header h3 {
  margin: 0;
  color: var(--ina-content-primary);
  font-size: var(--idds-body-large-size);
  font-weight: var(--idds-weight-bold);
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-body-large-line);
}

.analisa-modal .close-btn {
  width: 40px;
  height: 40px;
  flex: 0 0 auto;
  border-radius: 10px;
  border: 1px solid transparent;
  background: #f1f5f9;
  color: #334155;
  font-size: 0;
  line-height: var(--idds-heading-h5-line);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.analisa-modal .close-btn:hover {
  border-color: var(--ina-stroke-primary);
  color: var(--ina-content-primary);
  background: var(--ina-background-tertiary);
}

.analisa-modal .modal-body {
  padding: 24px 28px 28px;
}

.analisa-design-form {
  display: block !important;
  min-height: 100%;
}

.analisa-design-form .detail-section {
  margin: 0;
  padding: 0 0 20px;
  border-bottom: 1px solid var(--ina-stroke-primary);
}

.analisa-design-form .detail-section:last-of-type {
  border-bottom: 0;
}

.analisa-design-form .detail-section h4 {
  margin: 0 0 14px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--ina-stroke-primary);
  color: var(--ina-primary-primary);
  font-size: var(--idds-body-small-size);
  font-weight: var(--idds-weight-bold);
  line-height: var(--idds-body-small-line);
}

.platform-badges,
.platform-badges-editable {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 0;
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
  border-radius: 8px;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-semibold);
  line-height: var(--idds-caption-line);
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
  margin: 0;
  padding: 12px 14px;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
  color: #64748b;
  font-size: var(--idds-caption-size);
  font-style: normal;
  line-height: var(--idds-caption-line);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  margin: 2px 0 0;
  padding: 14px 0 16px;
  gap: 10px;
  border-top: 1px solid #e2e8f0;
  position: sticky;
  bottom: 0;
  background: var(--ina-background-primary);
  z-index: 3;
}

.platform-add-group,
.interop-add-group {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 44px;
  gap: 8px;
  align-items: center;
}

.platform-select,
.interop-input,
.analisa-design-form input,
.analisa-design-form select {
  width: 100%;
  min-height: 44px;
  padding: 9px 12px;
  border: 1px solid #dbe3ef;
  border-radius: 10px;
  background: #ffffff;
  color: var(--ina-content-primary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.platform-select:focus,
.interop-input:focus,
.analisa-design-form input:focus,
.analisa-design-form select:focus {
  border-color: var(--ina-primary-primary);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(31, 63, 147, 0.12);
}

.btn-add-small {
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 10px;
  background: var(--ina-primary-primary);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  cursor: pointer;
  box-shadow: 0 8px 18px rgba(30, 58, 138, 0.14);
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
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  text-transform: uppercase;
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-caption-small-line);
}

.transaksi-item,
.transaksi-item-readonly {
  padding: 16px;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  background: #f8fafc;
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
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  color: var(--ina-content-primary);
  line-height: var(--idds-caption-line);
}

.transaksi-url {
  color: var(--ina-content-primary);
  background: #fff;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 6px;
  padding: 4px 8px;
  font-size: var(--idds-caption-small-size);
  word-break: break-all;
  line-height: var(--idds-caption-small-line);
}

.transaksi-meta {
  margin-top: 10px;
  color: var(--ina-content-secondary);
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.btn-icon-delete {
  width: 34px;
  height: 34px;
  padding: 0;
  border: 1px solid var(--ina-stroke-primary);
  border-radius: 8px;
  background: #fff;
  color: var(--ina-negative-600);
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
  width: 100%;
  min-height: 44px;
  justify-content: center;
  border: 1px dashed #bfdbfe;
  border-radius: 10px;
  background: #eff6ff;
  color: var(--ina-primary-primary);
  font-weight: var(--idds-weight-bold);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 14px;
  cursor: pointer;
}

.input-error {
  border-color: var(--ina-negative-600);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.field-error {
  margin: 6px 0 0;
  font-size: var(--idds-caption-small-size);
  color: var(--ina-negative-600);
  line-height: var(--idds-caption-small-line);
}

.form-hint {
  margin-top: 12px;
  font-size: var(--idds-caption-small-size);
  line-height: var(--idds-caption-small-line);
}

.form-hint.error {
  color: var(--ina-negative-600);
}

.upload-container {
  margin-top: 12px;
}

.custom-file-upload {
  display: block;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  padding: 18px;
  text-align: center;
  cursor: pointer;
  background-color: #f8fafc;
  transition: all 0.2s ease;
}

.custom-file-upload:hover {
  border-color: var(--ina-primary-primary);
  background-color: rgba(35, 131, 226, 0.05);
}

.file-input-hidden {
  display: none;
}

.upload-content {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr);
  align-items: center;
  justify-items: start;
  gap: 4px 12px;
  text-align: left;
}

.upload-icon {
  grid-row: span 2;
  width: 38px;
  height: 38px;
  padding: 8px;
  border-radius: 10px;
  background: #eef4ff;
  color: #1e3a8a;
}

.custom-file-upload:hover .upload-icon {
  color: var(--ina-primary-primary);
}

.upload-text {
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  font-size: var(--idds-caption-size);
  font-weight: var(--idds-weight-bold);
  color: #0f172a;
  line-height: var(--idds-caption-line);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upload-subtext {
  font-size: var(--idds-caption-small-size);
  color: var(--ina-content-secondary);
  line-height: var(--idds-caption-small-line);
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

.analisa-modal-header {
  flex: 0 0 auto;
  align-items: center;
  padding: 20px 24px !important;
  border-bottom: 1px solid #e2e8f0 !important;
  background: #ffffff !important;
  box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03) !important;
}

.analisa-title-block {
  min-width: 0;
}

.analisa-eyebrow {
  display: block;
  margin-bottom: 4px;
  color: #64748b;
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  letter-spacing: var(--idds-letter-spacing);
  line-height: var(--idds-caption-small-line);
  text-transform: uppercase;
}

.analisa-title-block h3 {
  color: #0f172a !important;
  font-size: var(--idds-heading-h5-size) !important;
  font-weight: var(--idds-weight-bold) !important;
  letter-spacing: var(--idds-letter-spacing) !important;
  line-height: var(--idds-heading-h5-line) !important;
}

.analisa-title-block p {
  margin: 4px 0 0;
  color: #475569;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.analisa-modal-body {
  flex: 1 1 auto;
  min-height: 0;
  padding: 18px 22px 0 !important;
  overflow: auto;
  background: #f7f9fc;
}

.analisa-loading {
  min-height: 420px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: #64748b;
}

.analisa-form-panels {
  min-width: 0;
  display: grid;
  gap: 14px;
  padding-bottom: 0;
}

.analisa-design-form .analysis-editor-card {
  scroll-margin-top: 18px;
  margin: 0 !important;
  padding: 18px !important;
  border: 1px solid #dbe3ef !important;
  border-radius: 8px;
  background: #ffffff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.045);
}

.analysis-section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid #edf2f7;
}

.analysis-section-title {
  min-width: 0;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.analysis-section-icon {
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: #eef4ff;
  color: #1e3a8a;
}

.analisa-design-form .analysis-editor-card h4 {
  margin: 0 !important;
  padding: 0 !important;
  border: 0 !important;
  color: #0f172a !important;
  font-size: var(--idds-body-small-size) !important;
  font-weight: var(--idds-weight-bold) !important;
  line-height: var(--idds-body-small-line) !important;
}

.analysis-section-title p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: var(--idds-caption-size);
  line-height: var(--idds-caption-line);
}

.analysis-section-count {
  flex: 0 0 auto;
  min-height: 28px;
  display: inline-flex;
  align-items: center;
  padding: 5px 9px;
  border: 1px solid #dbeafe;
  border-radius: 999px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: var(--idds-caption-small-size);
  font-weight: var(--idds-weight-bold);
  white-space: nowrap;
  line-height: var(--idds-caption-small-line);
}

.platform-add-group {
  grid-template-columns: minmax(0, 1fr);
}

.modal-actions .btn {
  min-height: 44px;
  border-radius: 10px;
  font-weight: var(--idds-weight-bold);
}

.modal-actions .btn:not(.btn-secondary) {
  background: #1e3a8a;
  color: #ffffff;
}

@media (max-width: 900px) {
  .analisa-modal {
    width: calc(100vw - 24px);
    height: calc(100vh - 24px);
    max-height: calc(100vh - 24px);
  }

  .analisa-modal-body {
    padding: 14px 14px 0 !important;
  }

  .modal-actions {
    padding-bottom: 14px;
  }
}

@media (max-width: 640px) {
  .analisa-modal-header {
    padding: 16px !important;
  }

  .analisa-title-block h3 {
    font-size: var(--idds-body-size) !important;
    line-height: var(--idds-body-line) !important;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .analysis-section-header {
    flex-direction: column;
  }

  .analysis-section-count {
    align-self: flex-start;
  }

  .upload-content {
    grid-template-columns: 1fr;
    justify-items: center;
    text-align: center;
  }

  .upload-icon {
    grid-row: auto;
  }
}
</style>

