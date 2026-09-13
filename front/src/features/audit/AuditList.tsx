import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Badge,
  Code,
  Drawer,
  Group,
  JsonInput,
  Paper,
  ScrollArea,
  Select,
  Stack,
  Table,
  Text,
  TextInput,
  Timeline,
  Title,
} from '@mantine/core'
import { IconHistory } from '@tabler/icons-react'
import { DataTable } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import { api, unwrap } from '@/lib/api'
import { formatDateTime, fromNow } from '@/lib/format'
import type { AuditLog } from '@/types/api'

const EVENT_COLORS: Record<string, string> = {
  created: 'green',
  updated: 'blue',
  deleted: 'red',
  login: 'grape',
  logout: 'gray',
}

const EVENT_FILTER = [
  { value: '', label: 'Todos los eventos' },
  { value: 'created', label: 'Creaciones' },
  { value: 'updated', label: 'Actualizaciones' },
  { value: 'deleted', label: 'Eliminaciones' },
  { value: 'login', label: 'Sesiones iniciadas' },
  { value: 'logout', label: 'Sesiones cerradas' },
]

/** Visor de auditoría: quién hizo qué, cuándo y con qué diff (old/new). */
export default function AuditList() {
  const [search, setSearch] = useState('')
  const [event, setEvent] = useState('')
  const [detail, setDetail] = useState<AuditLog | null>(null)

  const list = useListQuery<AuditLog>({
    resource: 'audit',
    endpoint: '/admin/audit',
    filters: { event: event || undefined },
  })

  const { data: causers } = useQuery({
    queryKey: ['audit', 'causers'],
    staleTime: 300_000,
    queryFn: () => api.get<{ data: Array<{ id: number; name: string }> }>('/admin/audit/causers').then((r) => unwrap(r)),
  })

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap" gap="xs">
        <Title order={3}>Auditoría</Title>
        <Group gap="xs" wrap="nowrap">
          <Select
            data={EVENT_FILTER}
            value={event}
            onChange={(value) => {
              setEvent(value ?? '')
              list.setPage(1)
            }}
            w={190}
            allowDeselect={false}
          />
          <Select
            data={[
              { value: '', label: 'Todos los usuarios' },
              ...(causers ?? []).map((u) => ({ value: String(u.id), label: u.name })),
            ]}
            value={list.getFilter('causer_id')}
            onChange={(value) => list.setFilter('causer_id', value)}
            w={200}
            allowDeselect={false}
          />
        </Group>
      </Group>

      <TextInput
        placeholder="Buscar en descripción o registro afectado…"
        value={search}
        onChange={(e) => setSearch(e.currentTarget.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') list.setSearch(search)
        }}
        w={340}
      />

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay actividad registrada"
        columns={[
          { key: 'created_at', label: 'Fecha', width: 170, sortable: true, render: (log) => (
            <Stack gap={0}>
              <Text size="sm">{formatDateTime(log.created_at)}</Text>
              <Text size="xs" c="dimmed">{fromNow(log.created_at)}</Text>
            </Stack>
          ) },
          { key: 'causer', label: 'Usuario', render: (log) => log.causer?.name ?? 'sistema' },
          {
            key: 'event',
            label: 'Acción',
            render: (log) => (
              <Badge variant="light" color={EVENT_COLORS[log.event ?? ''] ?? 'gray'}>
                {log.event ?? log.log_name}
              </Badge>
            ),
          },
          { key: 'description', label: 'Descripción' },
          { key: 'subject_type', label: 'Registro', render: (log) => (
            <Text size="sm" c="dimmed">
              {log.subject_type ? `${shortModel(log.subject_type)} #${log.subject_id}` : '—'}
            </Text>
          ), hideOnMobile: true },
        ]}
        pagination={{
          page: list.page,
          lastPage: list.lastPage,
          total: list.total,
          perPage: list.perPage,
          onPageChange: list.setPage,
          onPerPageChange: list.setPerPage,
        }}
        onRowClick={(log) => setDetail(log)}
      />

      <AuditDetailDrawer log={detail} onClose={() => setDetail(null)} />
    </Stack>
  )
}

function shortModel(classFqn: string): string {
  return classFqn.split('\\').pop() ?? classFqn
}

function AuditDetailDrawer({ log, onClose }: { log: AuditLog | null; onClose: () => void }) {
  const attributes = log?.properties?.attributes ?? null
  const old = log?.properties?.old ?? null
  const changedKeys = attributes ? Object.keys(attributes) : old ? Object.keys(old) : []

  return (
    <Drawer opened={Boolean(log)} onClose={onClose} title="Detalle de actividad" size="lg" position="right">
      {log && (
        <Stack gap="md">
          <Paper withBorder p="sm">
            <Group gap="xs">
              <IconHistory size={16} />
              <Text fw={500}>{log.description}</Text>
            </Group>
            <Text size="sm" c="dimmed" mt={4}>
              {log.causer?.name ?? 'sistema'} · {formatDateTime(log.created_at)} ·{' '}
              {log.subject_type ? `${shortModel(log.subject_type)} #${log.subject_id}` : '—'}
            </Text>
          </Paper>

          {log.event === 'updated' && attributes && old && (
            <ScrollArea>
              <Table verticalSpacing="xs" withTableBorder>
                <Table.Thead>
                  <Table.Tr>
                    <Table.Th>Campo</Table.Th>
                    <Table.Th>Antes</Table.Th>
                    <Table.Th>Después</Table.Th>
                  </Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {changedKeys.map((key) => (
                    <Table.Tr key={key}>
                      <Table.Td>
                        <Code>{key}</Code>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm" c="red" lineClamp={2}>
                          {JSON.stringify(old[key])}
                        </Text>
                      </Table.Td>
                      <Table.Td>
                        <Text size="sm" c="green" lineClamp={2}>
                          {JSON.stringify(attributes[key])}
                        </Text>
                      </Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            </ScrollArea>
          )}

          {log.event === 'created' && attributes && (
            <JsonInput label="Valores creados" value={JSON.stringify(attributes, null, 2)} readOnly autosize minRows={8} />
          )}

          {log.event === 'deleted' && old && (
            <JsonInput label="Valores eliminados" value={JSON.stringify(old, null, 2)} readOnly autosize minRows={8} />
          )}

          {(log.event === 'login' || log.event === 'logout') && (
            <Timeline active={1} bulletSize={20}>
              <Timeline.Item title="Sesión">
                {log.event === 'login' ? 'Iniciada' : 'Cerrada'}
              </Timeline.Item>
              <Timeline.Item title="Usuario">{log.causer?.name}</Timeline.Item>
            </Timeline>
          )}
        </Stack>
      )}
    </Drawer>
  )
}
