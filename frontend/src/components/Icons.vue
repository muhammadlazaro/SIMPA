<script setup>
import { computed, useAttrs } from 'vue'
import {
  IconActivity,
  IconAlertCircle,
  IconAlertTriangle,
  IconArrowLeft,
  IconBell,
  IconBox,
  IconChartBar,
  IconCheck,
  IconChevronDown,
  IconChevronLeft,
  IconChevronRight,
  IconCircleCheck,
  IconCircle,
  IconCirclePlus,
  IconCircleX,
  IconCode,
  IconClock,
  IconClipboardCheck,
  IconDeviceDesktop,
  IconDots,
  IconDotsVertical,
  IconDownload,
  IconEdit,
  IconEye,
  IconEyeOff,
  IconFile,
  IconFileTypeDoc,
  IconFileTypePdf,
  IconFileText,
  IconFilter,
  IconFlask,
  IconFolderPlus,
  IconHelpCircle,
  IconInbox,
  IconLayoutDashboard,
  IconLayoutSidebarLeftCollapse,
  IconLayoutSidebarLeftExpand,
  IconList,
  IconLoader2,
  IconLock,
  IconLogout,
  IconMenu2,
  IconMessageCircle,
  IconPackages,
  IconPlus,
  IconRefresh,
  IconDeviceFloppy,
  IconSearch,
  IconServer,
  IconSettings,
  IconShieldCheck,
  IconStack2,
  IconTrash,
  IconUpload,
  IconUser,
  IconX,
} from '@tabler/icons-vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
  size: {
    type: Number,
    default: 20,
  },
})

const attrs = useAttrs()

const icons = {
  activity: IconActivity,
  alert: IconAlertCircle,
  'alert-circle': IconAlertCircle,
  'alert-triangle': IconAlertTriangle,
  apps: IconPackages,
  'arrow-left': IconArrowLeft,
  bell: IconBell,
  chart: IconChartBar,
  check: IconCheck,
  'check-circle': IconCircleCheck,
  circle: IconCircle,
  'chevron-down': IconChevronDown,
  'chevron-left': IconChevronLeft,
  'chevron-right': IconChevronRight,
  code: IconCode,
  clock: IconClock,
  checklist: IconClipboardCheck,
  dashboard: IconLayoutDashboard,
  design: IconStack2,
  download: IconDownload,
  edit: IconEdit,
  ellipsis: IconDots,
  'ellipsis-vertical': IconDotsVertical,
  eye: IconEye,
  'eye-off': IconEyeOff,
  file: IconFile,
  'file-doc': IconFileTypeDoc,
  'file-pdf': IconFileTypePdf,
  'file-text': IconFileText,
  filter: IconFilter,
  flask: IconFlask,
  'folder-plus': IconFolderPlus,
  grid: IconBox,
  inbox: IconInbox,
  list: IconList,
  'sidebar-collapse': IconLayoutSidebarLeftCollapse,
  'sidebar-expand': IconLayoutSidebarLeftExpand,
  loader: IconLoader2,
  lock: IconLock,
  logout: IconLogout,
  menu: IconMenu2,
  message: IconMessageCircle,
  monitor: IconDeviceDesktop,
  plus: IconPlus,
  'plus-circle': IconCirclePlus,
  refresh: IconRefresh,
  'refresh-cw': IconRefresh,
  save: IconDeviceFloppy,
  search: IconSearch,
  server: IconServer,
  settings: IconSettings,
  shield: IconShieldCheck,
  trash: IconTrash,
  upload: IconUpload,
  user: IconUser,
  x: IconX,
  'x-circle': IconCircleX,
}

const iconComponent = computed(() => icons[props.name] || IconHelpCircle)
const strokeWidth = computed(() => {
  if (props.size >= 64) return 5
  if (props.size >= 24) return 2
  if (props.size >= 20) return 1.5
  return 1.25
})
</script>

<template>
  <component
    :is="iconComponent"
    v-bind="attrs"
    :size="size"
    :stroke-width="strokeWidth"
    aria-hidden="true"
    focusable="false"
  />
</template>
