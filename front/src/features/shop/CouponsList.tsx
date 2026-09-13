import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Button,
  Group,
  Modal,
  NumberInput,
  Radio,
  Stack,
  Switch,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import { IconPlus } from '@tabler/icons-react'
import { DataTable, type DataColumn } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import { couponsService, type CouponRecord } from '@/features/shop/shopService'
import { formatMoney } from '@/lib/format'
import { validationErrors } from '@/lib/api'

/** Gestión de cupones: porcentaje o importe fijo, límites y validez. */
export default function CouponsList() {
  const [editing, setEditing] = useState<CouponRecord | 'new' | null>(null)
  const list = useListQuery<CouponRecord>({ resource: 'coupons', endpoint: '/admin/coupons' })

  const columns: DataColumn<CouponRecord>[] = [
    { key: 'code', label: 'Código', sortable: true, render: (coupon) => (
      <Text ff="monospace" fw={600}>{coupon.code}</Text>
    ) },
    { key: 'type', label: 'Descuento', render: (coupon) =>
      coupon.type === 'percentage' ? `${coupon.percentage} %` : formatMoney((coupon.amount_cents ?? 0) / 100) },
    { key: 'min_subtotal_cents', label: 'Mínimo compra', hideOnMobile: true, render: (coupon) =>
      coupon.min_subtotal_cents ? formatMoney(coupon.min_subtotal_cents / 100) : '—' },
    { key: 'usage_count', label: 'Usos', render: (coupon) =>
      `${coupon.usage_count}${coupon.usage_limit ? ` / ${coupon.usage_limit}` : ''}` },
    { key: 'ends_at', label: 'Válido hasta', hideOnMobile: true, render: (coupon) => coupon.ends_at ?? 'Siempre' },
    { key: 'is_active', label: 'Activo', render: (coupon) => (
      <Text size="sm" c={coupon.is_active ? 'green' : 'red'}>{coupon.is_active ? 'Sí' : 'No'}</Text>
    ) },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Title order={3}>Cupones</Title>
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
          Nuevo cupón
        </Button>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay cupones"
        columns={columns}
        pagination={{
          page: list.page,
          lastPage: list.lastPage,
          total: list.total,
          perPage: list.perPage,
          onPageChange: list.setPage,
          onPerPageChange: list.setPerPage,
        }}
        actions={{
          onEdit: (coupon) => setEditing(coupon),
          onDelete: async (coupon) => {
            await couponsService.delete(coupon.id)
            list.reload()
          },
        }}
      />

      {editing && (
        <CouponFormModal
          coupon={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            list.reload()
          }}
        />
      )}
    </Stack>
  )
}

function CouponFormModal({
  coupon,
  onClose,
  onSaved,
}: {
  coupon: CouponRecord | null
  onClose: () => void
  onSaved: () => void
}) {
  const queryClient = useQueryClient()
  const form = useForm({
    mode: 'uncontrolled',
    initialValues: {
      code: coupon?.code ?? '',
      type: coupon?.type ?? 'percentage',
      percentage: coupon?.percentage ?? 10,
      amount: coupon?.amount_cents ? coupon.amount_cents / 100 : 5,
      min_subtotal: coupon?.min_subtotal_cents ? coupon.min_subtotal_cents / 100 : null,
      usage_limit: coupon?.usage_limit ?? null,
      starts_at: coupon?.starts_at ?? null,
      ends_at: coupon?.ends_at ?? null,
      is_active: coupon?.is_active ?? true,
    },
    validate: {
      code: (v) => (/^[A-Za-z0-9_-]{3,30}$/.test(v) ? null : 'Código no válido (3-30, sin espacios)'),
    },
  })

  const save = useMutation({
    mutationFn: (values: typeof form.values) =>
      couponsService.save({
        ...(coupon ? { id: coupon.id } : {}),
        code: values.code.toUpperCase(),
        type: values.type,
        percentage: values.type === 'percentage' ? values.percentage : null,
        amount_cents: values.type === 'fixed' ? Math.round(values.amount * 100) : null,
        min_subtotal_cents: values.min_subtotal !== null ? Math.round(values.min_subtotal * 100) : null,
        usage_limit: values.usage_limit,
        starts_at: values.starts_at,
        ends_at: values.ends_at,
        is_active: values.is_active,
      }),
    onSuccess: () => {
      notifications.show({ message: 'Cupón guardado.', color: 'green' })
      void queryClient.invalidateQueries({ queryKey: ['coupons'] })
      onSaved()
    },
    onError: (error) => {
      const errors = validationErrors(error)
      Object.entries(errors).forEach(([field, message]) => form.setFieldError(field, message))
    },
  })

  return (
    <Modal opened onClose={onClose} title={coupon ? `Cupón: ${coupon.code}` : 'Nuevo cupón'} size="34rem" centered>
      <form onSubmit={form.onSubmit((values) => save.mutate(values))}>
        <Stack gap="sm">
          <TextInput label="Código" placeholder="VERANO25" key={form.key('code')} {...form.getInputProps('code')} />
          <Radio.Group
            label="Tipo de descuento"
            key={form.key('type')}
            {...form.getInputProps('type')}
          >
            <Group mt="xs">
              <Radio value="percentage" label="Porcentaje" />
              <Radio value="fixed" label="Importe fijo" />
            </Group>
          </Radio.Group>

          {form.values.type === 'percentage' ? (
            <NumberInput label="Porcentaje (%)" min={1} max={100} key={form.key('percentage')} {...form.getInputProps('percentage')} />
          ) : (
            <NumberInput label="Importe (€)" min={0} decimalScale={2} decimalSeparator="," key={form.key('amount')} {...form.getInputProps('amount')} />
          )}

          <Group grow>
            <NumberInput
              label="Compra mínima (€)"
              min={0}
              decimalScale={2}
              decimalSeparator=","
              key={form.key('min_subtotal')}
              {...form.getInputProps('min_subtotal')}
            />
            <NumberInput
              label="Límite de usos"
              min={1}
              key={form.key('usage_limit')}
              {...form.getInputProps('usage_limit')}
            />
          </Group>
          <Group grow>
            <DatePickerInput
              label="Desde"
              clearable
              valueFormat="DD/MM/YYYY"
              locale="es"
              key={form.key('starts_at')}
              {...form.getInputProps('starts_at')}
            />
            <DatePickerInput
              label="Hasta"
              clearable
              valueFormat="DD/MM/YYYY"
              locale="es"
              key={form.key('ends_at')}
              {...form.getInputProps('ends_at')}
            />
          </Group>
          <Switch label="Activo" key={form.key('is_active')} {...form.getInputProps('is_active', { type: 'checkbox' })} />

          <Group justify="flex-end" mt="sm">
            <Button variant="subtle" color="gray" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={save.isPending}>
              Guardar cupón
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
