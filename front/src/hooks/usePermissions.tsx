import type { ReactNode } from 'react'
import { useAuthStore } from '@/store/authStore'

/**
 * Acceso declarativo a los permisos del usuario autenticado.
 * Los permisos llegan del backend (`GET /auth/me`) ya resueltos:
 * roles heredados + permisos directos + comodín `*` para super-admin.
 */
export function usePermissions() {
  const can = useAuthStore((s) => s.can)
  const user = useAuthStore((s) => s.user)

  return {
    user,
    can,
    cannot: (permission: string | string[]) => !can(permission),
    is: (role: string | string[]) => {
      if (!user) return false
      const roles = user.roles.map((r) => r.name)
      return Array.isArray(role) ? role.some((r) => roles.includes(r)) : roles.includes(role)
    },
  }
}

/** Renderiza `children` solo si el usuario tiene el permiso (o alguno de ellos). */
export function Can({
  permission,
  children,
  fallback = null,
}: {
  permission: string | string[]
  children: ReactNode
  fallback?: ReactNode
}) {
  const { can } = usePermissions()
  return <>{can(permission) ? children : fallback}</>
}
