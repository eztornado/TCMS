import { Group, Menu, Stack, Text, Title } from '@mantine/core'
import { Badge } from '@mantine/core'
import { DataTable } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import { bookingsService, type BookingRecord } from '@/features/events/eventsService'
import { formatDateTime, formatMoney } from '@/lib/format'

const STATUS: Record<string, { label: string; color: string }> = {
  pending: { label: 'Pendiente', color: 'orange' },
  confirmed: { label: 'Confirmada', color: 'green' },
  cancelled: { label: 'Cancelada', color: 'red' },
  attended: { label: 'Asistió', color: 'blue' },
  no_show: { label: 'No presentó', color: 'gray' },
}

/** Listado de reservas de eventos con cambio rápido de estado. */
export default function BookingsList() {
  const list = useListQuery<BookingRecord>({
    resource: 'bookings',
    endpoint: '/admin/bookings',
  })

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <Title order={3}>Reservas</Title>
        <Text size="sm" c="dimmed">
          {list.total} reservas
        </Text>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay reservas"
        columns={[
          { key: 'reference', label: 'Código', width: 130 },
          { key: 'customer_name', label: 'Cliente', render: (booking) => (
            <Stack gap={0}>
              <Text size="sm" fw={500}>{booking.customer_name}</Text>
              <Text size="xs" c="dimmed">{booking.customer_email}</Text>
            </Stack>
          ) },
          { key: 'event_title', label: 'Evento', render: (booking) => booking.event_title ?? '—' },
          { key: 'session_starts_at', label: 'Sesión', hideOnMobile: true, render: (booking) => formatDateTime(booking.session_starts_at) },
          { key: 'seats', label: 'Plazas', width: 80, textAlign: 'center' },
          { key: 'amount_cents', label: 'Importe', render: (booking) => (booking.amount_cents ? formatMoney(booking.amount_cents / 100) : '—') },
          { key: 'status', label: 'Estado', render: (booking) => (
            <StatusMenu booking={booking} onSaved={list.reload} />
          ) },
          { key: 'created_at', label: 'Creada', hideOnMobile: true, render: (booking) => formatDateTime(booking.created_at) },
        ]}
        pagination={{
          page: list.page,
          lastPage: list.lastPage,
          total: list.total,
          perPage: list.perPage,
          onPageChange: list.setPage,
          onPerPageChange: list.setPerPage,
        }}
      />
    </Stack>
  )
}

/** El estado se cambia pulsando sobre el badge. */
function StatusMenu({ booking, onSaved }: { booking: BookingRecord; onSaved: () => void }) {
  return (
    <Menu withinPortal>
      <Menu.Target>
        <Badge variant="light" color={STATUS[booking.status]?.color ?? 'gray'} style={{ cursor: 'pointer' }}>
          {STATUS[booking.status]?.label ?? booking.status}
        </Badge>
      </Menu.Target>
      <Menu.Dropdown>
        {Object.entries(STATUS).map(([value, { label }]) => (
          <Menu.Item
            key={value}
            disabled={value === booking.status}
            onClick={async () => {
              await bookingsService.updateStatus(booking.id, value)
              onSaved()
            }}
          >
            {label}
          </Menu.Item>
        ))}
      </Menu.Dropdown>
    </Menu>
  )
}
