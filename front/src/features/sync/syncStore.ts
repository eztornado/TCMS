import { create } from 'zustand'
import { notifications } from '@mantine/notifications'
import { syncService, type SyncState, type SyncSummary } from './syncService'

interface SyncStore {
  state: SyncState | null
  syncing: boolean
  lastError: string | null

  /** Carga el estado (60 s de sondeo desde SyncIndicator). */
  refresh(): Promise<void>
  /** Lanza el ciclo push+pull+files contra el central. */
  run(): Promise<void>
}

export const useSyncStore = create<SyncStore>((set, get) => ({
  state: null,
  syncing: false,
  lastError: null,

  async refresh() {
    if (get().syncing) {
      return
    }

    try {
      const state = await syncService.state()
      set({ state, lastError: null })
    } catch {
      // Sin estado (error local) no molesta: la app nativa igual está arrancando.
    }
  },

  async run() {
    if (get().syncing) {
      return
    }

    set({ syncing: true })

    try {
      const summary: SyncSummary = await syncService.run()

      notifications.show({
        title: 'Sincronización completada',
        message: `${summary.applied} aplicados · ${summary.conflicts} conflictos · ${summary.files_uploaded} subidas · ${summary.files_downloaded} descargas`,
        color: 'teal',
      })

      await get().refresh()
    } catch (error) {
      const message = error instanceof Error ? error.message : 'No se pudo sincronizar.'

      set({ lastError: message })
      notifications.show({ title: 'Sincronización', message, color: 'red' })
    } finally {
      set({ syncing: false })
    }
  },
}))
