<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import http from '../lib/http'
import { getHomeByRole } from '../constants/roles'

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const emailInput = ref(null)

const router = useRouter()
const auth = useAuthStore()

// Auto-focus email field on mount
onMounted(() => {
  emailInput.value?.focus()
})

// Validate email format
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

async function submit() {
  error.value = ''

  // Client-side validation
  if (!email.value.trim()) {
    error.value = 'Email harus diisi'
    return
  }

  if (!isValidEmail(email.value)) {
    error.value = 'Format email tidak valid'
    return
  }

  if (!password.value) {
    error.value = 'Password harus diisi'
    return
  }

  if (password.value.length < 8) {
    error.value = 'Password minimal 8 karakter'
    return
  }

  loading.value = true
  try {
    const normalizedEmail = email.value.trim()
    const { data } = await http.post('/login', {
      email: normalizedEmail,
      password: password.value
    })
    // New standardized response format
    auth.setToken(data.data.token)
    auth.setUser(data.data.user)

    const role = data.data.user?.role
    router.push(getHomeByRole(role).path)
  } catch (e) {
    // Handle validation errors from Laravel
    if (e?.response?.status === 422) {
      const errors = e.response.data?.errors
      if (errors) {
        // Get first error message
        const firstError = Object.values(errors)[0]
        error.value = Array.isArray(firstError) ? firstError[0] : firstError
      } else {
        error.value = e.response.data?.message || 'Terjadi kesalahan validasi'
      }
    } else if (e?.response?.data?.message) {
      error.value = e.response.data.message
    } else {
      error.value = 'Login gagal. Periksa email dan password Anda.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-container">
    <div class="cyber-mesh" aria-hidden="true"></div>
    <div class="login-box">
      <!-- Header -->
      <div class="login-header">
        <div class="login-brand-mark">
          <img src="/bssn.png" alt="BSSN" />
        </div>
        <h1>Sistem Manajemen Pengembangan Aplikasi</h1>
        <p>Silakan masuk untuk melanjutkan</p>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="error-alert" role="alert">
        {{ error }}
      </div>

      <form @submit.prevent="submit" id="loginForm" autocomplete="username current-password" novalidate>
        <!-- Email Field -->
        <div class="form-group">
          <label for="email">Email</label>
          <input id="email" ref="emailInput" v-model="email" name="username" type="email" autocomplete="username"
            inputmode="email" autocapitalize="off" autocorrect="off" placeholder="nama@example.com" aria-label="Email"
            aria-required="true" :disabled="loading" />
        </div>

        <!-- Password Field -->
        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" v-model="password" name="password" type="password" autocomplete="current-password"
            placeholder="Masukkan password" aria-label="Password" aria-required="true" :disabled="loading" />
        </div>

        <!-- Submit Button -->
        <button class="btn btn-login" :disabled="loading" type="submit" :aria-busy="loading">
          <span v-if="loading" class="loading-spinner"></span>
          <span>{{ loading ? 'Memproses...' : 'Masuk' }}</span>
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-container {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  padding: 24px;
  background: #0b2348;
}

.login-container::before {
  content: '';
  position: absolute;
  inset: -18px;
  z-index: -2;
  background:
    linear-gradient(135deg, rgba(7, 24, 55, 0.68), rgba(26, 95, 152, 0.48)),
    url('/bssn-building-login.png') center 190% / cover no-repeat;
  filter: blur(2px) saturate(1.08);
  transform: scale(1.02);
}

.login-container::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  background:
    radial-gradient(circle at 50% 24%, rgba(233, 244, 255, 0.26), transparent 200%),
    linear-gradient(180deg, rgba(13, 42, 84, 0.16), rgba(5, 17, 38, 0.42));
}

.cyber-mesh {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}

.cyber-mesh::before,
.cyber-mesh::after {
  content: '';
  position: absolute;
  background-repeat: no-repeat;
  background-size: contain;
  opacity: 0.82;
  mix-blend-mode: normal;
  filter:
    drop-shadow(0 0 5px rgba(0, 116, 255, 0.54))
    drop-shadow(0 0 14px rgba(0, 88, 210, 0.28));
}

.cyber-mesh::before {
  width: min(760px, 62vw);
  height: min(560px, 62vh);
  right: -70px;
  top: -58px;
  background-image: url("data:image/svg+xml,%3Csvg width='760' height='560' viewBox='0 0 760 560' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%230075ff' stroke-opacity='.38' stroke-width='1.2'%3E%3Cpath d='M38 134 144 88 246 126 334 58 466 96 592 42 724 112M144 88 192 212 334 58M246 126 304 238 466 96M466 96 526 216 724 112M78 326 192 212 304 238 414 318 526 216 654 302 724 112M192 212 238 392 414 318M304 238 366 446 526 216M414 318 498 468 654 302M78 326 238 392 366 446 498 468 706 424M654 302 706 424 724 112'/%3E%3Cpath d='M38 134 78 326M592 42 654 302M238 392 304 238M366 446 414 318M498 468 526 216' stroke-opacity='.24'/%3E%3C/g%3E%3Cg fill='%230077ff' fill-opacity='.72'%3E%3Ccircle cx='38' cy='134' r='7'/%3E%3Ccircle cx='144' cy='88' r='5'/%3E%3Ccircle cx='246' cy='126' r='7'/%3E%3Ccircle cx='334' cy='58' r='5'/%3E%3Ccircle cx='466' cy='96' r='8'/%3E%3Ccircle cx='592' cy='42' r='5'/%3E%3Ccircle cx='724' cy='112' r='8'/%3E%3Ccircle cx='192' cy='212' r='8'/%3E%3Ccircle cx='304' cy='238' r='6'/%3E%3Ccircle cx='526' cy='216' r='7'/%3E%3Ccircle cx='78' cy='326' r='6'/%3E%3Ccircle cx='414' cy='318' r='8'/%3E%3Ccircle cx='654' cy='302' r='6'/%3E%3Ccircle cx='238' cy='392' r='8'/%3E%3Ccircle cx='366' cy='446' r='7'/%3E%3Ccircle cx='498' cy='468' r='8'/%3E%3Ccircle cx='706' cy='424' r='6'/%3E%3C/g%3E%3Cg fill='%2342b8ff' fill-opacity='.48'%3E%3Ccircle cx='118' cy='258' r='3'/%3E%3Ccircle cx='282' cy='338' r='3'/%3E%3Ccircle cx='442' cy='202' r='3'/%3E%3Ccircle cx='586' cy='352' r='3'/%3E%3Ccircle cx='686' cy='210' r='3'/%3E%3C/g%3E%3C/svg%3E");
}

.cyber-mesh::after {
  width: min(680px, 70vw);
  height: min(360px, 42vh);
  left: -110px;
  bottom: -70px;
  opacity: 0.62;
  transform: rotate(-7deg);
  background-image: url("data:image/svg+xml,%3Csvg width='680' height='360' viewBox='0 0 680 360' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23007dff' stroke-opacity='.34' stroke-width='1.2'%3E%3Cpath d='M22 288 116 218 214 248 318 174 432 206 546 128 658 162M116 218 168 98 318 174M214 248 276 66 432 206M432 206 482 48 658 162M22 288 214 248 332 316 546 128M168 98 276 66 482 48M332 316 432 206 658 162'/%3E%3C/g%3E%3Cg fill='%230b7cff' fill-opacity='.66'%3E%3Ccircle cx='22' cy='288' r='7'/%3E%3Ccircle cx='116' cy='218' r='6'/%3E%3Ccircle cx='214' cy='248' r='8'/%3E%3Ccircle cx='318' cy='174' r='7'/%3E%3Ccircle cx='432' cy='206' r='8'/%3E%3Ccircle cx='546' cy='128' r='7'/%3E%3Ccircle cx='658' cy='162' r='6'/%3E%3Ccircle cx='168' cy='98' r='5'/%3E%3Ccircle cx='276' cy='66' r='6'/%3E%3Ccircle cx='482' cy='48' r='5'/%3E%3Ccircle cx='332' cy='316' r='8'/%3E%3C/g%3E%3C/svg%3E");
}

:deep(.login-box),
.login-box {
  position: relative;
  z-index: 1;
  max-width: 440px;
  padding: 36px 34px;
  border-radius: 16px;
  border: 1px solid rgba(255, 255, 255, 0.58);
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 26px 70px rgba(5, 18, 38, 0.34);
  backdrop-filter: blur(12px);
}

:global(#app .login-box) {
  border-radius: 16px !important;
  box-shadow: 0 26px 70px rgba(5, 18, 38, 0.34) !important;
}

/* Login Header - Notion Style */
.login-header {
  text-align: center;
  margin-bottom: 28px;
}

.login-brand-mark {
  width: 78px;
  height: 78px;
  margin: 0 auto 16px;
  border: 0;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: none;
  overflow: visible;
}

.login-brand-mark img {
  width: 78px;
  height: 78px;
  object-fit: contain;
  filter: drop-shadow(0 10px 20px rgba(8, 25, 54, 0.18));
}

.login-header h1 {
  font-family: var(--notion-font-brand);
  font-size: 21px;
  font-weight: 800;
  color: var(--notion-text);
  margin: 0 0 8px 0;
  line-height: 1.3;
}

.login-header p {
  font-size: 14px;
  color: var(--notion-text-secondary);
  margin: 0;
}

/* Error Alert - Notion Style */
.error-alert {
  background-color: var(--notion-red-bg);
  color: var(--notion-red);
  padding: 10px 14px;
  border-radius: 8px;
  margin-bottom: 20px;
  border: 1px solid rgba(235, 87, 87, 0.3);
  font-size: 14px;
  line-height: 1.5;
}

/* Form Group - Notion Style */
.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  color: var(--notion-text);
  font-size: 14px;
  font-weight: 500;
}

.form-group input {
  width: 100%;
  padding: 11px 12px;
  border: 1px solid var(--notion-border);
  border-radius: 8px;
  font-size: 14px;
  transition: all 0.15s ease;
  background-color: var(--notion-bg);
  color: var(--notion-text);
}

.form-group input:focus {
  outline: none;
  border-color: var(--notion-blue);
  box-shadow: rgba(35, 131, 226, 0.14) 0px 0px 0px 3px;
}

.form-group input:hover:not(:focus) {
  background-color: var(--notion-hover);
}

.form-group input:disabled {
  background-color: var(--notion-bg-secondary);
  cursor: not-allowed;
  opacity: 0.6;
}

.form-group input::placeholder {
  color: var(--notion-text-secondary);
  opacity: 0.6;
}

/* Login Button - Notion Style */
.btn-login {
  width: 100%;
  margin-top: 20px;
  padding: 11px 16px;
  font-size: 14px;
  font-weight: 500;
  background-color: var(--notion-blue);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-login:hover:not(:disabled) {
  background-color: var(--notion-blue-hover);
}

.btn-login:active:not(:disabled) {
  transform: scale(0.98);
}

.btn-login:disabled {
  background-color: color-mix(in srgb, var(--notion-blue) 60%, white);
  cursor: not-allowed;
}

/* Loading Spinner */
.loading-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 520px) {
  .login-container {
    padding: 16px;
  }

  .login-box {
    padding: 28px 22px;
  }

  .login-header h1 {
    font-size: 18px;
  }
}
</style>
