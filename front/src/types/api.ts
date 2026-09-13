/** Tipos compartidos que describen los sobres de respuesta del API Laravel. */

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type LaravelResource<T> = { data: T }

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

/** Registro de auditoría (spatie activitylog serializado). */
export interface AuditLog {
  id: number
  log_name: string
  description: string
  event: string | null
  subject_type: string | null
  subject_id: number | null
  causer_type: string | null
  causer_id: number | null
  causer?: { id: number; name: string; email: string } | null
  properties: {
    old?: Record<string, unknown>
    attributes?: Record<string, unknown>
    [k: string]: unknown
  } | null
  batch_uuid: string | null
  created_at: string
}

export interface Role {
  id: number
  name: string
  label?: string | null
  permissions: Permission[]
  users_count?: number
}

export interface Permission {
  id: number
  name: string
  label?: string | null
  group: string
}

export interface User {
  id: number
  name: string
  email: string
  roles: Pick<Role, 'id' | 'name' | 'label'>[]
  permissions: string[]
  is_active?: boolean
  email_verified_at?: string | null
  last_login_at?: string | null
  created_at?: string
  updated_at?: string
}

/** Fila de configuración del sitio (GET /admin/settings). */
export interface Setting {
  id: number
  group: string
  key: string
  value: unknown
  type: string
  is_public: boolean
  label?: string
}

/** Ítem de menú construido en el backend según permisos del usuario. */
export interface MenuItem {
  label: string
  to: string
  icon?: string
  permission?: string
  children?: MenuItem[]
  badge?: string
}

export interface MediaItem {
  id: number
  name: string
  file_name: string
  mime_type: string
  size: number
  url: string
  thumbnail_url?: string | null
  collection_name: string
  created_at: string
}
