import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Badge,
  Box,
  Button,
  Divider,
  Drawer,
  Group,
  SegmentedControl,
  Select,
  Stack,
  Table,
  TextInput,
  Text,
  Textarea,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { DataTable, type DataColumn } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import { ordersService, type OrderRecord } from '@/features/shop/shopService'
import { formatDateTime, formatMoney } from '@/lib/format'
import { errorMessage } from '@/lib/api'

const STATUS: Record<string, { label: string; color: string }> = {
  pending: { label: 'Pendiente', color: 'orange' },
  processing: { label: 'En proceso', color: 'blue' },
  completed: { label: 'Completado', color: 'green' },
  cancelled: { label: 'Cancelado', color: 'red' },
  refunded: { label: 'Reembolsado', color: 'grape' },
}

const PAYMENT: Record<string, { label: string; color: string }> = {
  pending: { label: 'Sin pagar', color: 'orange' },
  paid: { label: 'Pagado', color: 'green' },
  failed: { label: 'Fallido', color: 'red' },
  refunded: { label: 'Devuelto', color: 'grape' },
}

/** Gestión de pedidos: detalle con líneas, historial y transiciones de estado. */
export default function OrdersList() {
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [detailId, setDetailId] = useState<number | null>(null)

  const list = useListQuery<OrderRecord>({
    resource: 'orders',
    endpoint: '/admin/orders',
    search,
    filters: { status: status || undefined },
  })

  const columns: DataColumn<OrderRecord>[] = [
    { key: 'number', label: 'Número', sortable: true },
    { key: 'customer_name', label: 'Cliente', render: (order) => (
      <Stack gap={0}>
        <Text size="sm" fw={500}>{order.customer_name ?? '—'}</Text>
        <Text size="xs" c="dimmed">{order.email}</Text>
      </Stack>
    ) },
    { key: 'items', label: 'Artículos', hideOnMobile: true, render: (order) => order.items?.length ?? '…' },
    { key: 'total_cents', label: 'Total', sortable: true, render: (order) => formatMoney(order.total_cents / 100) },
    { key: 'payment_status', label: 'Pago', render: (order) => (
      <Badge size="sm" variant="light" color={PAYMENT[order.payment_status]?.color ?? 'gray'}>
        {PAYMENT[order.payment_status]?.label ?? order.payment_status}
      </Badge>
    ) },
    { key: 'status', label: 'Estado', sortable: true, render: (order) => (
      <Badge size="sm" variant="light" color={STATUS[order.status]?.color ?? 'gray'}>
        {STATUS[order.status]?.label ?? order.status}
      </Badge>
    ) },
    { key: 'placed_at', label: 'Fecha', sortable: true, hideOnMobile: true, render: (order) => formatDateTime(order.placed_at) },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <Title order={3}>Pedidos</Title>
        <Group wrap="nowrap">
          <SegmentedControl
            size="xs"
            data={[
              { value: '', label: 'Todos' },
              ...Object.entries(STATUS).map(([value, { label }]) => ({ value, label })),
            ]}
            value={status}
            onChange={setStatus}
          />
          <TextInput
            placeholder="Nº de pedido o email…"
            value={search}
            onChange={(e) => setSearch(e.currentTarget.value)}
            onKeyDown={(e) => e.key === 'Enter' && list.setSearch(search)}
            w={240}
          />
        </Group>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay pedidos"
        columns={columns}
        onRowClick={(order) => setDetailId(order.id)}
        pagination={{
          page: list.page,
          lastPage: list.lastPage,
          total: list.total,
          perPage: list.perPage,
          onPageChange: list.setPage,
          onPerPageChange: list.setPerPage,
        }}
        sorting={{ by: list.sort, dir: list.dir, onSort: list.toggleSort }}
      />

      <OrderDetailDrawer orderId={detailId} onClose={() => setDetailId(null)} onChanged={list.reload} />
    </Stack>
  )
}

function OrderDetailDrawer({
  orderId,
  onClose,
  onChanged,
}: {
  orderId: number | null
  onClose: () => void
  onChanged: () => void
}) {
  const queryClient = useQueryClient()
  const [note, setNote] = useState('')

  const { data: order, isLoading } = useQuery({
    queryKey: ['orders', orderId],
    queryFn: () => ordersService.get(orderId!),
    enabled: Boolean(orderId),
  })

  const transition = useMutation({
    mutationFn: ({ status }: { status: string }) =>
      ordersService.changeStatus(orderId!, status, note || undefined),
    onSuccess: () => {
      notifications.show({ message: 'Estado del pedido actualizado.', color: 'green' })
      setNote('')
      void queryClient.invalidateQueries({ queryKey: ['orders'] })
      onChanged()
    },
    onError: (error) => notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' }),
  })

  const refund = useMutation({
    mutationFn: () => ordersService.refund(orderId!),
    onSuccess: () => {
      notifications.show({ message: 'Reembolso registrado.', color: 'green' })
      void queryClient.invalidateQueries({ queryKey: ['orders'] })
      onChanged()
    },
    onError: (error) => notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' }),
  })

  return (
    <Drawer opened={Boolean(orderId)} onClose={onClose} title={order ? `Pedido ${order.number}` : 'Pedido'} size="lg" position="right">
      {isLoading || !order ? (
        <Text c="dimmed">Cargando…</Text>
      ) : (
        <Stack gap="md">
          <Group justify="space-between">
            <div>
              <Text size="sm" c="dimmed">Cliente</Text>
              <Text fw={500}>{order.customer_name ?? '—'}</Text>
              <Text size="sm" c="dimmed">{order.email}</Text>
            </div>
            <div style={{ textAlign: 'right' }}>
              <Text size="sm" c="dimmed">Plazado</Text>
              <Text fw={500}>{formatDateTime(order.placed_at)}</Text>
            </div>
          </Group>

          <Table verticalSpacing="xs" withTableBorder>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Artículo</Table.Th>
                <Table.Th ta="center">Cant.</Table.Th>
                <Table.Th ta="right">Total</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {(order.items ?? []).map((item) => (
                <Table.Tr key={item.id}>
                  <Table.Td>
                    <Text size="sm">{item.title}</Text>
                    {item.sku && <Text size="xs" c="dimmed" ff="monospace">{item.sku}</Text>}
                  </Table.Td>
                  <Table.Td ta="center">{item.quantity}</Table.Td>
                  <Table.Td ta="right">{formatMoney(item.total_cents / 100)}</Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>

          <Stack gap={4}>
            <Group justify="space-between"><Text size="sm">Subtotal</Text><Text size="sm">{formatMoney(order.subtotal_cents / 100)}</Text></Group>
            {order.discount_cents > 0 && (
              <Group justify="space-between"><Text size="sm" c="green">Descuento {order.coupon_code ? `(${order.coupon_code})` : ''}</Text><Text size="sm" c="green">−{formatMoney(order.discount_cents / 100)}</Text></Group>
            )}
            <Group justify="space-between"><Text size="sm">Impuestos</Text><Text size="sm">{formatMoney(order.tax_cents / 100)}</Text></Group>
            <Group justify="space-between"><Text size="sm">Envío</Text><Text size="sm">{formatMoney(order.shipping_cents / 100)}</Text></Group>
            <Divider />
            <Group justify="space-between"><Text fw={600}>Total</Text><Text fw={700}>{formatMoney(order.total_cents / 100)}</Text></Group>
          </Stack>

          {(order.shipping_address ?? null) && (
            <Box>
              <Text size="sm" c="dimmed">Dirección de envío</Text>
              <Text size="sm">
                {Object.values(order.shipping_address ?? {}).filter(Boolean).join(' · ')}
              </Text>
            </Box>
          )}

          <Divider label="Historial" labelPosition="center" />
          <Timeline bulletSize={12}>
            {(order.history ?? []).map((entry) => (
              <Timeline.Item
                key={entry.id}
                title={`${STATUS[entry.to_status]?.label ?? entry.to_status}`}
                color={STATUS[entry.to_status]?.color}
              >
                <Text size="xs" c="dimmed">
                  {formatDateTime(entry.created_at)} {entry.note ? `· ${entry.note}` : ''}
                </Text>
              </Timeline.Item>
            ))}
          </Timeline>

          <Divider label="Acciones" labelPosition="center" />
          <Textarea
            placeholder="Nota interna para la transición (opcional)"
            autosize
            minRows={2}
            value={note}
            onChange={(e) => setNote(e.currentTarget.value)}
          />
          <Group gap="xs">
            <Select
              placeholder="Cambiar estado…"
              w={200}
              data={(order.allowed_transitions ?? []).map((value) => ({
                value,
                label: STATUS[value]?.label ?? value,
              }))}
              onChange={(value) => value && transition.mutate({ status: value })}
              disabled={(order.allowed_transitions ?? []).length === 0}
            />
            <Button
              variant="light"
              color="grape"
              disabled={order.payment_status !== 'paid'}
              loading={refund.isPending}
              onClick={() => refund.mutate()}
            >
              Reembolsar
            </Button>
          </Group>
        </Stack>
      )}
    </Drawer>
  )
}
