import { QueryClient } from '@tanstack/react-query'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
      staleTime: 30_000,
    },
  },
})

/** Invalida todas las queries de un recurso tras una mutación. */
export function invalidate(resource: string): void {
  void queryClient.invalidateQueries({ queryKey: [resource] })
}
