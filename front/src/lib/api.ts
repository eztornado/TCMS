import axios, { AxiosError, type AxiosInstance, type AxiosRequestConfig } from 'axios'
import { notifications } from '@mantine/notifications'
import { config } from '@/config'

/**
 * Cliente API único (singleton) para el backend Laravel con autenticación SPA
 * de Sanctum por cookies de sesión.
 *
 * - Con `withCredentials` las cookies de sesión viajan en cada petición.
 * - Antes del login hay que pedir el CSRF cookie (`GET /sanctum/csrf-cookie`);
 *   `ensureCsrf()` lo hace una única vez por sesión y reintenta si caduca (419).
 * - Gestión global de errores con notificaciones (los 401 redirigen a /login).
 */
class ApiClient {
  private axiosInstance: AxiosInstance
  private csrfPromise: Promise<void> | null = null

  constructor() {
    this.axiosInstance = axios.create({
      baseURL: config.apiUrl,
      withCredentials: true,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })

    this.axiosInstance.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => this.handleError(error),
    )
  }

  /** Pide el cookie CSRF una sola vez (y de nuevo si el backend responde 419). */
  ensureCsrf(force = false): Promise<void> {
    if (!this.csrfPromise || force) {
      this.csrfPromise = axios
        .get('/sanctum/csrf-cookie', {
          baseURL: config.apiUrl.replace(/\/api$/, ''),
          withCredentials: true,
        })
        .then(() => undefined)
    }
    return this.csrfPromise
  }

  get<T>(url: string, cfg?: AxiosRequestConfig): Promise<T> {
    return this.axiosInstance.get<T>(url, cfg).then((r) => r.data)
  }
  post<T>(url: string, body?: unknown, cfg?: AxiosRequestConfig): Promise<T> {
    return this.axiosInstance.post<T>(url, body, cfg).then((r) => r.data)
  }
  put<T>(url: string, body?: unknown, cfg?: AxiosRequestConfig): Promise<T> {
    return this.axiosInstance.put<T>(url, body, cfg).then((r) => r.data)
  }
  patch<T>(url: string, body?: unknown, cfg?: AxiosRequestConfig): Promise<T> {
    return this.axiosInstance.patch<T>(url, body, cfg).then((r) => r.data)
  }
  delete<T>(url: string, cfg?: AxiosRequestConfig): Promise<T> {
    return this.axiosInstance.delete<T>(url, cfg).then((r) => r.data)
  }

  upload<T>(url: string, formData: FormData, onProgress?: (pct: number) => void): Promise<T> {
    return this.axiosInstance
      .post<T>(url, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (onProgress && e.total) onProgress(Math.round((e.loaded / e.total) * 100))
        },
      })
      .then((r) => r.data)
  }

  download(url: string, filename: string, cfg?: AxiosRequestConfig): Promise<void> {
    return this.axiosInstance
      .get<Blob>(url, { ...cfg, responseType: 'blob' })
      .then((response) => {
        const href = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        link.href = href
        link.download = filename
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(href)
      })
  }

  private async handleError(error: AxiosError): Promise<never> {
    const status = error.response?.status
    const url = error.config?.url ?? ''

    // Cookie CSRF caducada: se re-pide y se reintenta una vez.
    if (status === 419 && !(error.config as RetryConfig | undefined)?._retried) {
      const cfg = error.config as RetryConfig
      cfg._retried = true
      await this.ensureCsrf(true)
      return this.axiosInstance.request(cfg)
    }

    if (status === 401 && !url.includes('/auth/login') && !url.includes('/auth/me')) {
      window.location.href = '/login?expired=1'
    } else if (status === 403) {
      notifications.show({
        title: 'Sin permisos',
        message: 'No tienes permiso para realizar esta acción.',
        color: 'red',
      })
    } else if (status === 422) {
      // Los errores de validación los gestiona cada formulario.
    } else if (status && status >= 500) {
      notifications.show({
        title: 'Error del servidor',
        message: 'Se ha producido un error inesperado. Inténtalo de nuevo.',
        color: 'red',
      })
    }
    throw error
  }
}

interface RetryConfig extends AxiosRequestConfig {
  _retried?: boolean
}

export const api = new ApiClient()

/** Extrae el payload de datos de una respuesta Laravel (resource o collection). */
export function unwrap<T>(payload: { data: T } | T): T {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    return (payload as { data: T }).data
  }
  return payload
}

/** Extrae los errores de validación (422) a un diccionario campo → mensaje. */
export function validationErrors(error: unknown): Record<string, string> {
  const axiosError = error as AxiosError<{ errors?: Record<string, string[]> }>
  const errors = axiosError?.response?.data?.errors
  if (!errors) return {}
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0] ?? '']))
}

export function errorMessage(error: unknown, fallback = 'Ha ocurrido un error'): string {
  const axiosError = error as AxiosError<{ message?: string }>
  return axiosError?.response?.data?.message ?? axiosError?.message ?? fallback
}
