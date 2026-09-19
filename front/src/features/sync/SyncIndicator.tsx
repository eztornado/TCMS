import { useEffect } from 'react'
import { ActionIcon, Badge, Group, Tooltip } from '@mantine/core'
import { IconRefresh } from '@tabler/icons-react'
import { useSyncStore } from './syncStore'

const POLL_MS = 60_000

/**
 * Indicador de sincronización del panel nativo: pendientes de push, último
 * sync y botón manual. Invisible en el central (runtime web), donde no hay
 * nada que sincronizar.
 */
export default function SyncIndicator() {
  const { state, syncing, refresh, run } = useSyncStore()

  useEffect(() => {
    refresh().catch(() => undefined)

    const interval = setInterval(() => refresh().catch(() => undefined), POLL_MS)

    return () => clearInterval(interval)
  }, [refresh])

  if (!state?.available) {
    return null
  }

  const lastSync = state.device?.last_synced_at
    ? new Date(state.device.last_synced_at).toLocaleString()
    : 'nunca'

  return (
    <Group gap={8} wrap="nowrap">
      <Tooltip label={`Pendientes de enviar: ${state.pending} · Último sync: ${lastSync}`}>
        <Badge
          variant="light"
          color={state.pending > 0 ? 'yellow' : state.connected ? 'teal' : 'gray'}
          leftSection={<span>⇅</span>}
        >
          {state.pending > 0 ? `${state.pending} pendientes` : 'Sincronizado'}
        </Badge>
      </Tooltip>

      <Tooltip label="Sincronizar ahora">
        <ActionIcon
          variant="subtle"
          loading={syncing}
          onClick={() => run().catch(() => undefined)}
          aria-label="Sincronizar ahora"
        >
          <IconRefresh size={16} />
        </ActionIcon>
      </Tooltip>
    </Group>
  )
}
