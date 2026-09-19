import { api, unwrap } from '@/lib/api'
import { createResource, type ApiResource } from '@/services/apiResource'
import type { AuditLog, LaravelResource, Permission, Role, Setting, User } from '@/types/api'

export interface UserPayload {
  id?: number
  name: string
  email: string
  password?: string
  is_active: boolean
  roles: string[]
}

export const usersService = createResource<User>('/admin/users') as ApiResource<User> & {
  save: (payload: UserPayload) => Promise<User>
}

/** Crea o actualiza según venga `id` (contrato único para el formulario). */
usersService.save = (payload: UserPayload): Promise<User> => {
  if (payload.id) {
    return api.put<{ data: User }>(`/admin/users/${payload.id}`, payload).then((r) => unwrap(r))
  }
  return api.post<{ data: User }>('/admin/users', payload).then((r) => unwrap(r))
}

export const rolesService = {
  async list(): Promise<Role[]> {
    return api.get<{ data: Role[] }>('/admin/roles').then((r) => unwrap(r))
  },
  async permissions(): Promise<Permission[]> {
    return api.get<{ data: Permission[] }>('/admin/permissions').then((r) => unwrap(r))
  },
  async save(payload: { id?: number; name: string; label?: string; permissions: string[] }): Promise<Role> {
    return payload.id
      ? api.put<{ data: Role }>(`/admin/roles/${payload.id}`, payload).then((r) => unwrap(r))
      : api.post<{ data: Role }>('/admin/roles', payload).then((r) => unwrap(r))
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/admin/roles/${id}`)
  },
}

export const auditService = createResource<AuditLog>('/admin/audit')

export const mediaService = {
  async list(query: Record<string, unknown> = {}): Promise<never> {
    return api.get('/admin/media', { params: query }) as never
  },
  async upload(file: File, onProgress?: (pct: number) => void): Promise<{ id: number; url: string }> {
    const data = new FormData()
    data.append('file', file)
    const response = await api.upload<LaravelResource<{ id: number; url: string }>>(
      '/admin/media',
      data,
      onProgress,
    )
    return unwrap(response)
  },
  async update(id: number, payload: { alt?: string; title?: string }): Promise<void> {
    await api.put(`/admin/media/${id}`, payload)
  },
  async delete(id: number): Promise<void> {
    await api.delete(`/admin/media/${id}`)
  },
}

export const settingsService = {
  async all(): Promise<Setting[]> {
    return api.get<{ data: Setting[] }>('/admin/settings').then((r) => unwrap(r))
  },
  async save(values: Record<string, unknown>): Promise<void> {
    // El API espera una lista de {key, value}, no un mapa clave→valor.
    const entries = Object.entries(values).map(([key, value]) => ({ key, value }))
    await api.put('/admin/settings', { settings: entries })
  },
}

/** Métricas del panel. */
export const dashboardService = {
  stats(): Promise<{ data: Record<string, number> }> {
    return api.get('/admin/dashboard')
  },
}
