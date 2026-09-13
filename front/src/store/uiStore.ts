import { create } from 'zustand'
import { persist } from 'zustand/middleware'

interface UiState {
  colorScheme: 'light' | 'dark' | 'auto'
  navCollapsed: boolean
  setColorScheme: (scheme: 'light' | 'dark' | 'auto') => void
  toggleNav: () => void
}

/** Preferencias de interfaz persistidas en localStorage. */
export const useUiStore = create<UiState>()(
  persist(
    (set) => ({
      colorScheme: 'auto',
      navCollapsed: false,
      setColorScheme: (colorScheme) => set({ colorScheme }),
      toggleNav: () => set((state) => ({ navCollapsed: !state.navCollapsed })),
    }),
    { name: 'tcms-ui' },
  ),
)
