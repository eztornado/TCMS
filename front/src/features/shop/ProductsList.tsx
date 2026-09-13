import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  Badge,
  Button,
  Group,
  Modal,
  NumberInput,
  Stack,
  Switch,
  Text,
  TextInput,
  Textarea,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { DataTable, type DataColumn } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import {
  productsService,
  type ProductRecord,
  type ProductVariant,
} from '@/features/shop/shopService'
import { formatMoney } from '@/lib/format'
import { validationErrors } from '@/lib/api'

const STATUS_COLORS: Record<string, string> = {
  active: 'green',
  draft: 'gray',
  archived: 'orange',
}

/** Catálogo de productos con variantes, precio y stock. */
export default function ProductsList() {
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<ProductRecord | 'new' | null>(null)
  const list = useListQuery<ProductRecord>({ resource: 'products', endpoint: '/admin/products', search })

  const columns: DataColumn<ProductRecord>[] = [
    { key: 'title', label: 'Producto', sortable: true, render: (product) => (
      <Stack gap={0}>
        <Text fw={500}>{product.title}</Text>
        <Text size="xs" c="dimmed" ff="monospace">{product.sku ?? product.slug}</Text>
      </Stack>
    ) },
    { key: 'price_cents', label: 'Precio', sortable: true, render: (product) => (product.price_cents === null ? '—' : formatMoney(product.price_cents / 100)) },
    { key: 'variants', label: 'Variantes', hideOnMobile: true, render: (product) => (product.variants?.length ?? 0) },
    { key: 'stock', label: 'Stock', render: (product) => product.track_stock ? (product.stock ?? '—') : '∞' },
    { key: 'in_stock', label: 'Disponible', hideOnMobile: true, render: (product) => (
      <Badge size="sm" variant="light" color={product.in_stock ? 'green' : 'red'}>
        {product.in_stock ? 'Sí' : 'Agotado'}
      </Badge>
    ) },
    { key: 'status', label: 'Estado', sortable: true, render: (product) => (
      <Badge size="sm" variant="light" color={STATUS_COLORS[product.status] ?? 'gray'}>{product.status}</Badge>
    ) },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <TextInput
          placeholder="Buscar producto…"
          value={search}
          onChange={(e) => setSearch(e.currentTarget.value)}
          onKeyDown={(e) => e.key === 'Enter' && list.setSearch(search)}
          w={300}
        />
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
          Nuevo producto
        </Button>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay productos"
        columns={columns}
        pagination={{
          page: list.page,
          lastPage: list.lastPage,
          total: list.total,
          perPage: list.perPage,
          onPageChange: list.setPage,
          onPerPageChange: list.setPerPage,
        }}
        sorting={{ by: list.sort, dir: list.dir, onSort: list.toggleSort }}
        actions={{
          onEdit: (product) => setEditing(product),
          onDelete: async (product) => {
            await productsService.delete(product.id)
            list.reload()
          },
        }}
      />

      {editing && (
        <ProductFormModal
          product={editing === 'new' ? null : editing}
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

function ProductFormModal({
  product,
  onClose,
  onSaved,
}: {
  product: ProductRecord | null
  onClose: () => void
  onSaved: () => void
}) {
  const queryClient = useQueryClient()

  const form = useForm<{
    title: string
    slug: string
    excerpt: string
    description: string
    status: string
    sku: string
    price: number | null
    compare_at: number | null
    stock: number | null
    track_stock: boolean
    is_featured: boolean
    variants: ProductVariant[]
  }>({
    mode: 'uncontrolled',
    initialValues: {
      title: product?.title ?? '',
      slug: product?.slug ?? '',
      excerpt: product?.excerpt ?? '',
      description: product?.description ?? '',
      status: product?.status ?? 'draft',
      sku: product?.sku ?? '',
      price: product?.price_cents ? product.price_cents / 100 : null,
      compare_at: product?.compare_at_cents ? product.compare_at_cents / 100 : null,
      stock: product?.stock ?? 0,
      track_stock: product?.track_stock ?? true,
      is_featured: product?.is_featured ?? false,
      variants: (product?.variants ?? []).map((variant) => ({
        ...variant,
        price: variant.price !== null && variant.price !== undefined ? variant.price / 100 : null,
      })),
    },
    validate: {
      title: (v) => (v.trim().length >= 2 ? null : 'Indica el título'),
    },
  })

  const save = useMutation({
    mutationFn: (values: typeof form.values) =>
      productsService.saveWithVariants({
        ...(product ? { id: product.id } : {}),
        slug: values.slug || undefined,
        title: values.title,
        excerpt: values.excerpt,
        description: values.description,
        status: values.status,
        sku: values.sku || null,
        price_cents: values.price !== null ? Math.round(values.price * 100) : null,
        compare_at_cents: values.compare_at !== null ? Math.round(values.compare_at * 100) : null,
        stock: values.stock,
        track_stock: values.track_stock,
        is_featured: values.is_featured,
        variants: values.variants,
      }),
    onSuccess: () => {
      notifications.show({ message: 'Producto guardado.', color: 'green' })
      void queryClient.invalidateQueries({ queryKey: ['products'] })
      onSaved()
    },
    onError: (error) => {
      const errors = validationErrors(error)
      Object.entries(errors).forEach(([field, message]) => form.setFieldError(field, message))
    },
  })

  return (
    <Modal opened onClose={onClose} title={product ? `Producto: ${product.title}` : 'Nuevo producto'} size="62rem" centered>
      <form onSubmit={form.onSubmit((values) => save.mutate(values))}>
        <Stack gap="sm">
          <Group grow>
            <TextInput label="Título" key={form.key('title')} {...form.getInputProps('title')} />
            <TextInput label="Slug (opcional)" key={form.key('slug')} {...form.getInputProps('slug')} />
            <TextInput label="SKU" key={form.key('sku')} {...form.getInputProps('sku')} />
          </Group>
          <Group grow align="flex-end">
            <NumberInput label="Precio €" min={0} decimalScale={2} decimalSeparator="," key={form.key('price')} {...form.getInputProps('price')} />
            <NumberInput label="Precio tachado €" min={0} decimalScale={2} decimalSeparator="," key={form.key('compare_at')} {...form.getInputProps('compare_at')} />
            <NumberInput label="Stock" min={0} key={form.key('stock')} {...form.getInputProps('stock')} />
            <Switch label="Controlar stock" mt="lg" key={form.key('track_stock')} {...form.getInputProps('track_stock', { type: 'checkbox' })} />
            <Switch label="Destacado" mt="lg" key={form.key('is_featured')} {...form.getInputProps('is_featured', { type: 'checkbox' })} />
          </Group>
          <Textarea label="Resumen" autosize minRows={2} key={form.key('excerpt')} {...form.getInputProps('excerpt')} />
          <Textarea
            label="Descripción (HTML)"
            autosize
            minRows={3}
            styles={{ input: { fontFamily: 'monospace', fontSize: 13 } }}
            key={form.key('description')}
            {...form.getInputProps('description')}
          />

          <Stack gap="xs">
            <Group justify="space-between">
              <Text fw={500} size="sm">Variantes (deja vacío para producto simple)</Text>
              <Button
                variant="light"
                size="compact-xs"
                leftSection={<IconPlus size={14} />}
                onClick={() =>
                  form.insertListItem('variants', {
                    sku: '',
                    name: '',
                    options: {},
                    price: null,
                    stock: 0,
                    track_stock: true,
                  })
                }
              >
                Añadir variante
              </Button>
            </Group>

            {form.values.variants.map((_variant, index) => (
              <Group key={form.key(`variants.${index}.sku`) ?? index} align="flex-end" gap="xs" wrap="nowrap">
                <TextInput
                  placeholder="SKU"
                  w={140}
                  key={form.key(`variants.${index}.sku`)}
                  {...form.getInputProps(`variants.${index}.sku`)}
                />
                <TextInput
                  placeholder="Nombre (Talla M / Rojo)"
                  style={{ flex: 1 }}
                  key={form.key(`variants.${index}.name`)}
                  {...form.getInputProps(`variants.${index}.name`)}
                />
                <NumberInput
                  placeholder="€"
                  w={110}
                  min={0}
                  decimalScale={2}
                  decimalSeparator=","
                  key={form.key(`variants.${index}.price`)}
                  {...form.getInputProps(`variants.${index}.price`)}
                />
                <NumberInput
                  placeholder="Stock"
                  w={90}
                  min={0}
                  key={form.key(`variants.${index}.stock`)}
                  {...form.getInputProps(`variants.${index}.stock`)}
                />
                <Switch
                  label="Stock"
                  mb={6}
                  key={form.key(`variants.${index}.track_stock`)}
                  {...form.getInputProps(`variants.${index}.track_stock`, { type: 'checkbox' })}
                />
                <Button
                  color="red"
                  variant="subtle"
                  mb={4}
                  px={8}
                  onClick={() => form.removeListItem('variants', index)}
                  aria-label="Eliminar variante"
                >
                  <IconTrash size={16} />
                </Button>
              </Group>
            ))}
          </Stack>

          <Group justify="flex-end" mt="sm">
            <Button variant="subtle" color="gray" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={save.isPending}>
              Guardar producto
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
