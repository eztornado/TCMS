import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { LoadingOverlay } from '@mantine/core'
import { useAuthStore } from '@/store/authStore'
import { usePermissions } from '@/hooks/usePermissions'

/** Guard de autenticación: redirige a /login conservando la ruta destino. */
export function RequireAuth() {
  const { user, isInitialized } = useAuthStore()
  const location = useLocation()

  if (!isInitialized) {
    return <LoadingOverlay visible zIndex={1000} overlayProps={{ blur: 2 }} />
  }
  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }
  return <Outlet />
}

/** Guard de permiso para una ruta completa (definida como `element`). */
export function RequirePermission({
  permission,
  children,
}: {
  permission: string | string[]
  children?: ReactNode
}) {
  const { can } = usePermissions()
  if (!can(permission)) {
    return (
      <Navigate
        to="/403"
        replace
        state={{ permission: Array.isArray(permission) ? permission.join(', ') : permission }}
      />
    )
  }
  return children ?? <Outlet />
}
