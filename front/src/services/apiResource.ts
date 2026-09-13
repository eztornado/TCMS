import { api, unwrap } from '@/lib/api'
import type { Paginated } from '@/types/api'

export interface ListQuery {
  page?: number
  per_page?: number
  q?: string
  sort?: string
  [key: string]: unknown
}

/**
 * Fábrica de servicios CRUD genéricos para un recurso del API.
 * Devuelve un objeto con el contrato estándar de listado/creación/edición,
 * de modo que todas las páginas del panel comparten la misma capa HTTP.
 */
export function createResource<T extends { id: number | string }>(endpoint: string) {
  return {
    endpoint,
    async list(query: ListQuery = {}): Promise<Paginated<T>> {
      const qs = Object.entries(query).reduce((params, [key, value]) => {
        if (value === undefined || value === null || value === '') return params
        if (Array.isArray(value)) {
          for (const item of value) params.append(`${key}[]`, String(item))
        } else {
          params.set(key, String(value))
        }
        return params
      }, new URLSearchParams())

      return api.get<Paginated<T>>(`${endpoint}${qs.size ? `?${qs}` : ''}`)
    },

    async get(id: number | string): Promise<T> {
      return api.get<{ data: T }>(`${endpoint}/${id}`).then((r) => unwrap(r))
    },

    async create(payload: Partial<T> | FormData): Promise<T> {
      return api.post<{ data: T }>(endpoint, payload).then((r) => unwrap(r))
    },

    async update(id: number | string, payload: Partial<T> | FormData): Promise<T> {
      const request =
        payload instanceof FormData
          ? api.post<{ data: T }>(`${endpoint}/${id}`, payload, {
              headers: { 'X-HTTP-Method-Override': 'PUT' },
            })
          : api.put<{ data: T }>(`${endpoint}/${id}`, payload)
      return request.then((r) => unwrap(r))
    },

    async delete(id: number | string): Promise<void> {
      await api.delete(`${endpoint}/${id}`)
    },

    /** Borrado masivo (el backend puede exponerlo o no). */
    async deleteMany(ids: Array<number | string>): Promise<void> {
      await Promise.all(ids.map((id) => api.delete(`${endpoint}/${id}`)))
    },
  }
}

export type ApiResource<T extends { id: number | string }> = ReturnType<
  typeof createResource<T>
>
