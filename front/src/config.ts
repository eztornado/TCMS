/**
 * Configuración central de la aplicación.
 * Único punto de verdad: URL del API, tamaños de página, límites de ficheros...
 */
export const config = {
  appName: 'TornadoCMS',
  appShortName: 'TCMS',

  /** El front llama siempre a /api (proxy de Vite en dev, mismo origen en prod). */
  apiUrl: import.meta.env.VITE_API_URL ?? '/api',

  pageSizeDefault: 25,
  pageSizeOptions: ['10', '25', '50', '100'],

  maxUploadSizeMb: 20,
  allowedImageTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],

  /** Moneda y locale por defecto (configurables por sitio desde el backend). */
  currency: 'EUR',
  locale: 'es-ES',
} as const

export type AppColorScheme = 'light' | 'dark' | 'auto'
