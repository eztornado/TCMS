import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notifications } from '@mantine/notifications'
import { useNavigate } from 'react-router-dom'
import { errorMessage, validationErrors } from '@/lib/api'

interface MutationHooksOptions<T> {
  /** Clave base de react-query del recurso (invalida al mutar). */
  resourceKey: string
  listUrl: string
  labels: { created?: string; updated?: string; deleted?: string }
  save: (payload: Partial<T>) => Promise<unknown>
}

/**
 * Par de mutaciones (guardar / borrar) con notificaciones, redirección al
 * listado y aplicación de errores de validación al formulario. Elimina la
 * repetición que había en cada página de reigreengroup (patrón useAdminSave).
 */
export function useSaveResource<T extends { id: number | string }>({
  resourceKey,
  listUrl,
  labels,
  save,
}: MutationHooksOptions<T>) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const mutation = useMutation({
    mutationFn: async (payload: Partial<T> & { id?: number | string }) => {
      const isUpdate = Boolean(payload.id)
      await save(payload)
      return { isUpdate }
    },
    onSuccess: ({ isUpdate }) => {
      void queryClient.invalidateQueries({ queryKey: [resourceKey] })
      notifications.show({
        title: 'Guardado',
        message: isUpdate ? (labels.updated ?? 'Cambios guardados.') : (labels.created ?? 'Creado correctamente.'),
        color: 'green',
      })
      navigate(listUrl)
    },
    onError: (error) => {
      const errors = validationErrors(error)
      notifications.show({
        title: 'No se pudo guardar',
        message: Object.values(errors)[0] ?? errorMessage(error),
        color: 'red',
      })
      return errors
    },
  })

  return {
    save: mutation.mutateAsync,
    saving: mutation.isPending,
  }
}

export function useDeleteResource(resourceKey: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async (remove: () => Promise<unknown>) => remove(),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: [resourceKey] })
      notifications.show({ message: 'Eliminado correctamente.', color: 'green' })
    },
    onError: (error) => {
      notifications.show({
        title: 'No se pudo eliminar',
        message: errorMessage(error),
        color: 'red',
      })
    },
  })
}
