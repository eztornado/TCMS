import dayjs from 'dayjs'
import localizedFormat from 'dayjs/plugin/localizedFormat'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/es'
import { config } from '@/config'

dayjs.extend(localizedFormat)
dayjs.extend(relativeTime)
dayjs.locale('es')

export const formatDate = (value?: string | null, pattern = 'L'): string =>
  value ? dayjs(value).format(pattern) : '—'

export const formatDateTime = (value?: string | null): string => formatDate(value, 'L LT')

export const fromNow = (value?: string | null): string => (value ? dayjs(value).fromNow() : '—')

const currencyFormatter = new Intl.NumberFormat(config.locale, {
  style: 'currency',
  currency: config.currency,
})

export const formatMoney = (value: number | string | null | undefined): string =>
  currencyFormatter.format(Number(value ?? 0))

export const formatNumber = (value: number | string | null | undefined): string =>
  new Intl.NumberFormat(config.locale).format(Number(value ?? 0))

export const formatBytes = (bytes: number): string => {
  const units = ['B', 'KB', 'MB', 'GB']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  return `${value.toFixed(value % 1 === 0 ? 0 : 1)} ${units[unit]}`
}

export { dayjs }
