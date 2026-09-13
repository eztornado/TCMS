import { useMutation, useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { notifications } from '@mantine/notifications'
import { authService } from '@/services/authService'
import { useAuthStore } from '@/store/authStore'
import { errorMessage, validationErrors } from '@/lib/api'

/** Carga inicial de sesión + menú al arrancar la app. */
export function useInitAuth() {
  const { setUser, setMenu, setInitialized } = useAuthStore()

  return useQuery({
    queryKey: ['auth', 'me'],
    queryFn: async () => {
      try {
        const user = await authService.me()
        setUser(user)
        const menu = await authService.menu().catch(() => [])
        setMenu(menu)
        return { authenticated: true }
      } catch {
        setUser(null)
        return { authenticated: false }
      } finally {
        setInitialized(true)
      }
    },
    staleTime: Infinity,
    retry: false,
  })
}

export function useLogin() {
  const navigate = useNavigate()
  const { setUser, setMenu } = useAuthStore()

  return useMutation({
    mutationFn: (credentials: { email: string; password: string; remember: boolean }) =>
      authService.login(credentials.email, credentials.password, credentials.remember),
    onSuccess: async (_data, variables) => {
      try {
        const user = await authService.me()
        setUser(user)
        const menu = await authService.menu().catch(() => [])
        setMenu(menu)
        notifications.show({
          title: `Bienvenido${user.name ? `, ${user.name}` : ''}`,
          message: 'Sesión iniciada correctamente.',
          color: 'green',
        })
        navigate(variables.remember ? '/' : '/', { replace: true })
      } catch {
        notifications.show({
          title: 'Error',
          message: 'No se pudo cargar el perfil de usuario.',
          color: 'red',
        })
      }
    },
    onError: (error) => {
      const errors = validationErrors(error)
      notifications.show({
        title: 'No se pudo iniciar sesión',
        message: errors.email ?? errorMessage(error, 'Credenciales incorrectas.'),
        color: 'red',
      })
    },
  })
}

export function useLogout() {
  const { logout } = useAuthStore()

  return useMutation({
    mutationFn: () => authService.logout(),
    onSettled: () => {
      logout()
      useAuthStore.getState().setInitialized(true)
      window.location.href = '/login'
    },
  })
}
