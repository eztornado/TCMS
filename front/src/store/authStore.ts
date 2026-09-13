import { create } from 'zustand'
import type { MenuItem, User } from '@/types/api'

interface AuthState {
  user: User | null
  menu: MenuItem[]
  isInitialized: boolean
  /** Login en curso (entre submit y fetch de /auth/me). */
  isAuthenticating: boolean

  setUser: (user: User | null) => void
  setMenu: (menu: MenuItem[]) => void
  setInitialized: (initialized: boolean) => void
  setAuthenticating: (value: boolean) => void
  logout: () => void
  /** Comprueba un permiso con el usuario cargado (false si no hay sesión). */
  can: (permission: string | string[]) => boolean
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  menu: [],
  isInitialized: false,
  isAuthenticating: false,

  setUser: (user) => set({ user }),
  setMenu: (menu) => set({ menu }),
  setInitialized: (isInitialized) => set({ isInitialized }),
  setAuthenticating: (isAuthenticating) => set({ isAuthenticating }),

  logout: () => set({ user: null, menu: [] }),

  can: (permission) => {
    const user = get().user
    if (!user) return false
    const granted = new Set(user.permissions)
    // Un rol con `*` (super-admin) lo ve todo.
    if (granted.has('*')) return true
    if (Array.isArray(permission)) return permission.some((p) => granted.has(p))
    return granted.has(permission)
  },
}))
