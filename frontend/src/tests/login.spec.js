import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import Login from '../views/Login.vue'

const mocks = vi.hoisted(() => ({
  post: vi.fn(),
}))

vi.mock('../lib/http', () => ({
  default: {
    post: mocks.post,
  },
}))

const TextFieldStub = {
  props: {
    modelValue: { type: String, default: '' },
    type: { type: String, default: 'text' },
    statusMessage: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  setup() {
    const inputRef = ref(null)
    return { inputRef }
  },
  template: `
    <label>
      <input
        ref="inputRef"
        :type="type"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <span v-if="statusMessage" role="alert">{{ statusMessage }}</span>
    </label>
  `,
}

const PasswordInputStub = {
  props: {
    modelValue: { type: String, default: '' },
    statusMessage: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  template: `
    <label>
      <input
        type="password"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <span v-if="statusMessage" role="alert">{{ statusMessage }}</span>
    </label>
  `,
}

const ButtonStub = {
  props: {
    disabled: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
  },
  template: '<button :type="type" :disabled="disabled"><slot /></button>',
}

const AlertStub = {
  props: {
    title: { type: String, default: '' },
    message: { type: String, default: '' },
  },
  template: '<div role="alert">{{ title }} {{ message }}</div>',
}

async function mountLogin() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', component: Login },
      { path: '/unit-kerja', component: { template: '<div>Unit Kerja</div>' } },
    ],
  })
  await router.push('/login')
  await router.isReady()

  const wrapper = mount(Login, {
    attachTo: document.body,
    global: {
      plugins: [createPinia(), router],
      stubs: {
        Alert: AlertStub,
        Button: ButtonStub,
        PasswordInput: PasswordInputStub,
        TextField: TextFieldStub,
      },
    },
  })

  return { router, wrapper }
}

describe('Login', () => {
  beforeEach(() => {
    localStorage.clear()
    mocks.post.mockReset()
  })

  it('validates email and password before contacting the API', async () => {
    const { wrapper } = await mountLogin()

    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Masukkan alamat email Anda.')

    await wrapper.get('input[type="email"]').setValue('alamat-tidak-valid')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Gunakan format email yang valid')

    await wrapper.get('input[type="email"]').setValue('unit@instansi.go.id')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Masukkan password Anda.')

    await wrapper.get('input[type="password"]').setValue('pendek')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Password minimal terdiri dari 8 karakter.')
    expect(mocks.post).not.toHaveBeenCalled()
  })

  it('stores a successful session and opens the role home page', async () => {
    mocks.post.mockResolvedValue({
      data: {
        data: {
          token: 'test-token',
          user: { id: 7, name: 'Unit Kerja', role: 'unit_kerja' },
        },
      },
    })
    const { router, wrapper } = await mountLogin()

    await wrapper.get('input[type="email"]').setValue('  unit@instansi.go.id  ')
    await wrapper.get('input[type="password"]').setValue('Password123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(mocks.post).toHaveBeenCalledWith('/login', {
      email: 'unit@instansi.go.id',
      password: 'Password123!',
    })
    expect(localStorage.getItem('smpa_token')).toBe('test-token')
    expect(JSON.parse(localStorage.getItem('smpa_user'))).toMatchObject({
      id: 7,
      role: 'unit_kerja',
    })
    expect(router.currentRoute.value.path).toBe('/unit-kerja')
  })

  it('shows field errors and safe fallback messages from failed login requests', async () => {
    mocks.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: {
          errors: {
            email: ['Kredensial tidak valid.'],
          },
        },
      },
    })
    const { wrapper } = await mountLogin()

    await wrapper.get('input[type="email"]').setValue('unit@instansi.go.id')
    await wrapper.get('input[type="password"]').setValue('Password123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Kredensial tidak valid.')

    mocks.post.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { errors: {} },
      },
    })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Periksa kembali data yang Anda masukkan.')

    mocks.post.mockRejectedValueOnce(new Error('Network unavailable'))
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Akun belum dapat diakses.')
  })
})
