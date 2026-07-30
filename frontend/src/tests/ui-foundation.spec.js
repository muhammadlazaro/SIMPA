import { mount } from '@vue/test-utils'
import axe from 'axe-core'
import { describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { SingleFileUpload } from '@idds/vue'
import '../idds-theme.css'
import AsyncState from '../components/AsyncState.vue'
import ConfirmationDrawer from '../components/ConfirmationDrawer.vue'
import IddsSelect from '../components/IddsSelect.vue'
import IconActionButton from '../components/IconActionButton.vue'
import IconActionCell from '../components/IconActionCell.vue'
import Icons from '../components/Icons.vue'
import MetricCard from '../components/MetricCard.vue'
import PageHeader from '../components/PageHeader.vue'
import PaginationBar from '../components/PaginationBar.vue'
import SearchField from '../components/SearchField.vue'
import StatusBadge from '../components/StatusBadge.vue'
import { useToastStore } from '../stores/toast'
import { resolveIddsFileSelection } from '../utils/fileUpload'

const iconStub = {
  template: '<span aria-hidden="true" />',
}

async function expectNoA11yViolations(element) {
  if (!element.isConnected) {
    document.body.appendChild(element)
  }
  const result = await axe.run(element, {
    rules: {
      'color-contrast': { enabled: false },
    },
  })
  expect(result.violations).toEqual([])
}

describe('UI foundation', () => {
  it('renders an accessible page heading and back link', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/', component: { template: '<div />' } },
        { path: '/dashboard', component: { template: '<div />' } },
      ],
    })

    const wrapper = mount(PageHeader, {
      props: {
        title: 'Daftar Aplikasi',
        description: 'Kelola seluruh aplikasi.',
        backTo: '/dashboard',
        backLabel: 'Dashboard',
      },
      global: {
        plugins: [router],
        stubs: {
          Icons: iconStub,
        },
      },
    })

    expect(wrapper.get('h1').text()).toBe('Daftar Aplikasi')
    expect(wrapper.find('.ina-breadcrumb').exists()).toBe(true)
    await wrapper.get('.ina-breadcrumb__link').trigger('click')
    await router.isReady()
    expect(router.currentRoute.value.path).toBe('/dashboard')
    await expectNoA11yViolations(wrapper.element)
  })

  it('exposes loading, error retry, and empty states', async () => {
    const loading = mount(AsyncState, {
      props: { loading: true, loadingLabel: 'Memuat aplikasi' },
      global: { stubs: { Icons: iconStub } },
    })
    expect(loading.get('output').attributes('aria-label')).toBe('Memuat aplikasi')
    expect(loading.findAll('.ina-skeleton')).toHaveLength(12)

    const error = mount(AsyncState, {
      props: { error: 'Jaringan terputus' },
      global: { stubs: { Icons: iconStub } },
    })
    expect(error.find('.ina-alert--critical').exists()).toBe(true)
    await error.get('button').trigger('click')
    expect(error.emitted('retry')).toHaveLength(1)
    await expectNoA11yViolations(error.element)

    const empty = mount(AsyncState, {
      props: {
        empty: true,
        emptyTitle: 'Belum ada pengajuan',
        emptyDescription: 'Buat pengajuan pertama Anda.',
      },
      global: { stubs: { Icons: iconStub } },
    })
    expect(empty.text()).toContain('Belum ada pengajuan')
    expect(empty.get('.ui-state-illustration').attributes('src')).toBe('/illustrations/empty-data.png')
  })

  it('uses metric cards as real toggle buttons when interactive', async () => {
    const wrapper = mount(MetricCard, {
      props: {
        label: 'Development',
        value: 12,
        interactive: true,
        active: true,
      },
      global: { stubs: { Icons: iconStub } },
    })

    const button = wrapper.get('button')
    expect(button.attributes('aria-pressed')).toBe('true')
    await button.trigger('click')
    expect(wrapper.emitted('select')).toHaveLength(1)
    await expectNoA11yViolations(wrapper.element)
  })

  it('clears search input without removing its accessible label', async () => {
    const wrapper = mount(SearchField, {
      props: {
        modelValue: 'SIMPA',
        label: 'Cari aplikasi',
        placeholder: 'Cari aplikasi',
      },
      global: { stubs: { Icons: iconStub } },
    })

    expect(wrapper.get('input').attributes('placeholder')).toBe('Cari aplikasi')
    expect(wrapper.find('.ina-input-search__input').exists()).toBe(true)
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([''])
    await expectNoA11yViolations(wrapper.element)
  })

  it('uses IDDS pagination and Tabler icon stroke weights', async () => {
    const pagination = mount(PaginationBar, {
      props: { page: 2, lastPage: 4, total: 37 },
    })

    expect(pagination.find('.ina-pagination').exists()).toBe(true)
    await expectNoA11yViolations(pagination.element)

    const smallIcon = mount(Icons, { props: { name: 'search', size: 16 } })
    const largeIcon = mount(Icons, { props: { name: 'search', size: 24 } })
    const stateIcon = mount(Icons, { props: { name: 'inbox', size: 64 } })
    expect(smallIcon.get('svg').attributes('stroke-width')).toBe('1.25')
    expect(largeIcon.get('svg').attributes('stroke-width')).toBe('2')
    expect(stateIcon.get('svg').attributes('stroke-width')).toBe('5')
  })

  it('renders direct table actions with accessible icon labels', async () => {
    const wrapper = mount(IconActionCell, {
      props: { label: 'Aksi aplikasi SIMPA' },
      slots: {
        default: IconActionButton,
      },
      global: {
        stubs: { Icons: iconStub },
      },
    })

    expect(wrapper.get('fieldset legend').text()).toBe('Aksi aplikasi SIMPA')
    expect(wrapper.find('[aria-haspopup="menu"]').exists()).toBe(false)
  })

  it('uses an illustrated responsive drawer for destructive confirmation', async () => {
    const wrapper = mount(ConfirmationDrawer, {
      attachTo: document.body,
      props: {
        modelValue: true,
        title: 'Hapus aplikasi',
        subject: 'SIMPA',
        description: 'Data tidak dapat dipulihkan.',
      },
    })

    expect(document.body.querySelector('.ina-drawer')).not.toBeNull()
    expect(document.body.querySelector('.confirmation-illustration')?.getAttribute('src')).toBe('/illustrations/confirm-delete.png')
    expect(document.body.textContent).toContain('SIMPA')
    await expectNoA11yViolations(document.body.querySelector('.ina-drawer'))
    wrapper.unmount()
  })

  it('gives IDDS dropdown triggers an explicit accessible name', async () => {
    const wrapper = mount(IddsSelect, {
      props: {
        modelValue: '',
        accessibleLabel: 'Filter tahap aplikasi',
        options: [
          { label: 'Semua tahap', value: '' },
          { label: 'Diajukan', value: 'diajukan' },
        ],
        placeholder: 'Filter tahap',
      },
    })

    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="combobox"]').attributes('aria-label')).toBe('Filter tahap aplikasi')
    await expectNoA11yViolations(wrapper.element)
  })

  it('renders filter dropdowns as a direct option list', async () => {
    const wrapper = mount(IddsSelect, {
      attachTo: document.body,
      props: {
        modelValue: 'diajukan',
        accessibleLabel: 'Filter tahap aplikasi',
        options: [
          { label: 'Semua tahap', value: '' },
          { label: 'Diajukan', value: 'diajukan' },
        ],
      },
    })

    await wrapper.get('[role="combobox"]').trigger('click')
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))

    const panel = document.body.querySelector('.ina-select-dropdown__panel')
    expect(panel).not.toBeNull()
    expect(panel.querySelector('.ina-select-dropdown__selection-title')).toBeNull()
    expect(panel.querySelector('.ina-select-dropdown__search')).toBeNull()
    expect(panel.querySelector('[class*="preview"]')).toBeNull()
    expect(panel.textContent).toContain('Semua tahap')
    expect(panel.textContent).toContain('Diajukan')
    wrapper.unmount()
  })

  it('keeps an IDDS dropdown panel aligned to its trigger width', async () => {
    const wrapper = mount(IddsSelect, {
      attachTo: document.body,
      props: {
        modelValue: '',
        options: [
          { label: 'Publik', value: 'publik' },
          { label: 'Internal', value: 'internal' },
        ],
        placeholder: 'Pilih jenis layanan',
        width: '100%',
        panelWidth: '100%',
      },
    })

    const trigger = wrapper.get('[role="combobox"]')
    trigger.element.getBoundingClientRect = () => ({
      width: 320,
      height: 48,
      top: 0,
      right: 320,
      bottom: 48,
      left: 0,
      x: 0,
      y: 0,
      toJSON() {},
    })

    await trigger.trigger('click')
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))

    const panel = document.body.querySelector('.ina-select-dropdown__panel')
    expect(panel).not.toBeNull()
    expect(panel.style.width).toBe('320px')
  })

  it('keeps every IDDS single-file upload inside its container', () => {
    const host = document.createElement('div')
    host.id = 'app'
    document.body.appendChild(host)

    const wrapper = mount({
      components: { SingleFileUpload },
      template: `
        <div style="width: 240px">
          <SingleFileUpload title="Pilih dokumen" description="PDF maksimal 8 MB" />
        </div>
      `,
    }, { attachTo: host })

    const uploadStyle = getComputedStyle(wrapper.get('.ina-single-file-upload').element)
    expect(uploadStyle.width).toBe('100%')
    expect(uploadStyle.minWidth).toBe('0px')
    expect(uploadStyle.maxWidth).toBe('100%')

    wrapper.unmount()
    host.remove()
  })

  it('accepts the IDDS valid-file event and distinguishes rejection from removal', () => {
    const pdf = new File(['%PDF-1.7'], 'formulir.pdf', { type: 'application/pdf' })

    expect(resolveIddsFileSelection(pdf, null)).toEqual({ file: pdf, error: '' })
    expect(resolveIddsFileSelection(null, {
      isValid: false,
      error: 'Signature file tidak sesuai.',
    })).toEqual({ file: null, error: 'Signature file tidak sesuai.' })
    expect(resolveIddsFileSelection(null, null)).toEqual({ file: null, error: '' })
  })

  it('maps application status tones to non-interactive IDDS badges', async () => {
    const wrapper = mount(StatusBadge, {
      props: { tone: 'success' },
      slots: { default: 'Operational' },
    })

    expect(wrapper.text()).toContain('Operational')
    expect(wrapper.find('.ina-badge').exists()).toBe(true)
    expect(wrapper.find('button').exists()).toBe(false)
    await expectNoA11yViolations(wrapper.element)
  })

  it('keeps only the latest toast so feedback remains focused', () => {
    setActivePinia(createPinia())
    const toast = useToastStore()

    toast.push('Data sedang diproses', 'info', 0)
    const firstId = toast.items[0].id
    toast.push('Data berhasil disimpan', 'success', 0)

    expect(toast.items).toHaveLength(1)
    expect(toast.items[0].id).not.toBe(firstId)
    expect(toast.items[0]).toMatchObject({
      message: 'Data berhasil disimpan',
      type: 'success',
    })
  })
})
