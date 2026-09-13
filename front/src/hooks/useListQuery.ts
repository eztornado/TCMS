import { useQuery, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api } from '@/lib/api'
import type { Paginated } from '@/types/api'

export interface UseListQueryOptions {
  /** Clave base de react-query, p. ej. 'users'. */
  resource: string
  /** Endpoint relativo al API, p. ej. '/admin/users'. */
  endpoint: string
  /** Búsqueda controlada desde la página (si no, se usa `?q=` de la URL). */
  search?: string
  /** Filtros extra que no viajan en la URL. */
  filters?: Record<string, unknown>
  /** Tamaño de página distinto de 25 por defecto. */
  perPage?: number
  enabled?: boolean
}

export interface UseListQueryResult<T> {
  data: T[]
  raw: Paginated<T> | undefined
  isLoading: boolean
  isFetching: boolean
  total: number
  lastPage: number
  page: number
  perPage: number
  search: string
  sort: string | null
  dir: 'asc' | 'desc' | null
  setPage: (page: number) => void
  setPerPage: (perPage: number) => void
  setSearch: (search: string) => void
  /** Filtro persistido en la URL (`?causer_id=2`). */
  getFilter: (key: string) => string
  setFilter: (key: string, value: string | null) => void
  /** Alterna la ordenación por una columna (asc → desc → nada). */
  toggleSort: (key: string) => void
  reload: () => void
}

/**
 * Estado de listado server-side estándar de TCMS:
 * - vive en la URL (compartible, back/forward del navegador)
 * - TanStack Query como caché con `placeholderData` (sin saltos al paginar)
 * - normaliza el sobre de Laravel en un único sitio
 */
export function useListQuery<T>(
  options: UseListQueryOptions,
): UseListQueryResult<T> {
  const { resource, endpoint, filters, search: controlledSearch, enabled = true } = options
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()

  const page = Number(searchParams.get('page') ?? 1)
  const perPage = Number(searchParams.get('per_page') ?? options.perPage ?? 25)
  const search = controlledSearch ?? searchParams.get('q') ?? ''
  const sortParam = searchParams.get('sort')

  const sort = sortParam?.replace(/^-/, '') ?? null
  const dir = sortParam?.startsWith('-') ? 'desc' : sortParam ? 'asc' : null

  const patchParams = useCallback(
    (changes: Record<string, string | null>) => {
      const next = new URLSearchParams(searchParams)
      for (const [key, value] of Object.entries(changes)) {
        if (value === null || value === '') next.delete(key)
        else next.set(key, value)
      }
      setSearchParams(next)
    },
    [searchParams, setSearchParams],
  )

  const queryKey = useMemo<QueryKey>(
    () => [resource, 'list', { page, perPage, search, sort: sortParam, filters }],
    [resource, page, perPage, search, sortParam, filters],
  )

  const queryString = useMemo(() => {
    const qs = new URLSearchParams()
    if (page > 1) qs.set('page', String(page))
    if (perPage !== 25) qs.set('per_page', String(perPage))
    if (search) qs.set('q', search)
    if (sortParam) {
      // Contrato del API: campo y dirección por separado.
      qs.set('sort', sort ?? sortParam)
      qs.set('dir', sortParam.startsWith('-') ? 'desc' : 'asc')
    }
    for (const [key, value] of Object.entries(filters ?? {})) {
      if (value === undefined || value === null || value === '') continue
      if (Array.isArray(value)) {
        for (const item of value) qs.append(`${key}[]`, String(item))
      } else {
        qs.set(key, String(value))
      }
    }
    return qs.toString()
  }, [page, perPage, search, sortParam, sort, filters])

  const query = useQuery({
    queryKey,
    enabled,
    placeholderData: (previous) => previous,
    queryFn: () => api.get<Paginated<T>>(`${endpoint}${queryString ? `?${queryString}` : ''}`),
  })

  // Normaliza los dos sobres que usa el API: Laravel paginator moderno
  // (paginación dentro de `meta`) y el clásico (campos en la raíz).
  const raw = query.data
  const envelope = (raw as { meta?: Partial<Paginated<T>> } | undefined)?.meta ?? raw

  return {
    data: raw?.data ?? [],
    raw,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    total: envelope?.total ?? 0,
    lastPage: envelope?.last_page ?? 1,
    page,
    perPage,
    search,
    sort,
    dir,
    setPage: (next) => patchParams({ page: next > 1 ? String(next) : null }),
    setPerPage: (next) => patchParams({ per_page: next !== 25 ? String(next) : null, page: null }),
    setSearch: (next) => patchParams({ q: next || null, page: null }),
    getFilter: (key) => searchParams.get(key) ?? '',
    setFilter: (key, value) => patchParams({ [key]: value, page: null }),
    toggleSort: (key) => {
      if (sort !== key) {
        patchParams({ sort: key })
      } else if (dir === 'asc') {
        patchParams({ sort: `-${key}` })
      } else {
        patchParams({ sort: null })
      }
    },
    reload: () => void queryClient.invalidateQueries({ queryKey: [resource] }),
  }
}
