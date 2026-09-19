import { api, unwrap } from '@/lib/api'

/**
 * Estado de sincronización que sirve el app LOCAL (BD sqlite embebida).
 * En el central (runtime web) `available` es false y el panel lo oculta.
 */
export interface SyncState {
  runtime: 'web' | 'native-desktop' | 'native-mobile'
  available: boolean
  connected: boolean
  pending: number
  device: {
    uuid: string
    label: string
    last_synced_at: string | null
    last_pull_cursor: number
  } | null
  last_run: {
    at: string
    pushed: number
    applied: number
    conflicts: number
    rejected: number
    files_uploaded: number
    files_downloaded: number
  } | null
}

export interface SyncSummary {
  pushed: number
  applied: number
  conflicts: number
  rejected: number
  files_uploaded: number
  files_downloaded: number
  cursor: number
}

export const syncService = {
  state(): Promise<SyncState> {
    return api.get<{ data: SyncState }>('/sync/state').then((r) => unwrap(r))
  },

  run(): Promise<SyncSummary> {
    return api.post<{ data: SyncSummary }>('/sync/run').then((r) => unwrap(r))
  },
}
