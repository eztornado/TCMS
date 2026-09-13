import { api, unwrap } from '@/lib/api'
import type { LaravelResource, MenuItem, User } from '@/types/api'

interface LoginResponse {
  message: string
  user: User
  two_factor?: boolean
}

export const authService = {
  /** Login SPA: obtiene CSRF cookie y autentica por sesión (cookies). */
  async login(email: string, password: string, remember = true): Promise<LoginResponse> {
    await api.ensureCsrf()
    return api.post<LoginResponse>('/auth/login', { email, password, remember })
  },

  me(): Promise<User> {
    return api.get<LaravelResource<User>>('/auth/me').then((r) => unwrap(r))
  },

  menu(): Promise<MenuItem[]> {
    return api.get<{ data: MenuItem[] }>('/auth/menu').then((r) => unwrap(r))
  },

  logout(): Promise<void> {
    return api.post<void>('/auth/logout').catch(() => undefined)
  },

  requestPasswordReset(email: string): Promise<{ message: string }> {
    return api.post('/auth/forgot-password', { email })
  },

  resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<{ message: string }> {
    return api.post('/auth/reset-password', payload)
  },

  changePassword(payload: {
    current_password: string
    password: string
    password_confirmation: string
  }): Promise<{ message: string }> {
    return api.post('/auth/change-password', payload)
  },
}
