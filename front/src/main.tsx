import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClientProvider } from '@tanstack/react-query'
import { MantineProvider } from '@mantine/core'
import { DatesProvider } from '@mantine/dates'
import { ModalsProvider } from '@mantine/modals'
import { Notifications } from '@mantine/notifications'
import { theme } from '@/theme'
import { queryClient } from '@/lib/queryClient'
import { useInitAuth } from '@/hooks/useAuth'
import { PageFallback } from '@/components/layout/PageFallback'
import Router from '@/router'
import '@mantine/core/styles.css'
import '@mantine/dates/styles.css'
import '@mantine/notifications/styles.css'
import '@mantine/dropzone/styles.css'
import '@mantine/code-highlight/styles.css'
import '@/styles/global.css'

/** Hidrata la sesión al arrancar antes de montar el router. */
function Bootstrap() {
  const { isLoading } = useInitAuth()
  if (isLoading) return <PageFallback />
  return <Router />
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <MantineProvider theme={theme} defaultColorScheme="auto">
        <DatesProvider settings={{ locale: 'es', firstDayOfWeek: 1 }}>
          <ModalsProvider>
            <Notifications position="top-right" />
            <Bootstrap />
          </ModalsProvider>
        </DatesProvider>
      </MantineProvider>
    </QueryClientProvider>
  </StrictMode>,
)
