import { useMemo, useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import {
  Badge,
  Box,
  Button,
  Group,
  Image,
  Loader,
  Modal,
  SegmentedControl,
  Stack,
  Text,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { DataTable, type DataColumn } from '@/components/crud/DataTable'
import { FieldRenderer } from '@/components/fields/FieldRenderer'
import { useListQuery } from '@/hooks/useListQuery'
import { cmsService } from '@/services/cmsService'
import { validationErrors } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { CustomModelSchema, EntryRecord, FieldSchema } from '@/types/schema'

/**
 * CRUD de entradas de un custom model: el listado y el formulario se
 * construyen a partir del esquema que envía el backend (schema-driven UI).
 */
export default function CustomModelEntries() {
  const { slug = '' } = useParams()
  const [status, setStatus] = useState('')
  const [editing, setEditing] = useState<EntryRecord | 'new' | null>(null)

  const { data: schema, isLoading: schemaLoading } = useQuery({
    queryKey: ['cm-schema', slug],
    queryFn: () => cmsService.schema(slug),
  })

  const list = useListQuery<EntryRecord>({
    resource: `cm-${slug}`,
    endpoint: `/cm/${slug}`,
    filters: { status: status || undefined },
  })

  const columns = useMemo<DataColumn<EntryRecord>[]>(() => {
    if (!schema) return []
    const result: DataColumn<EntryRecord>[] = [{ key: 'id', label: '#', sortable: true }]

    for (const field of schema.fields.filter((f) => f.show_in_list)) {
      result.push({
        key: field.name,
        label: field.label,
        sortable: field.is_sortable,
        render: (entry) =>
          renderCell(
            field,
            entry[`${field.name}_url`] ?? entry[field.name],
          ),
      })
    }

    if (schema.has_status) {
      result.push({
        key: 'status',
        label: 'Estado',
        render: (entry) => (
          <Badge size="sm" variant="light" color={entry.status === 'published' ? 'green' : 'gray'}>
            {String(entry.status)}
          </Badge>
        ),
      })
    }
    result.push({
      key: 'updated_at',
      label: 'Actualizado',
      hideOnMobile: true,
      sortable: true,
      render: (entry) => formatDateTime(entry.updated_at as string),
    })
    return result
  }, [schema])

  if (schemaLoading || !schema) return <Loader size="sm" />

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <Stack gap={2}>
          <Title order={3}>{schema.plural_label ?? schema.label}</Title>
          <Group gap={6}>
            <Badge variant="light" color="gray" ff="monospace">
              /api/cm/{schema.slug}
            </Badge>
            <Button component="a" href={`/content/${slug}/edit`} variant="subtle" size="compact-sm">
              Editar esquema
            </Button>
          </Group>
        </Stack>
        <Group wrap="nowrap">
          {schema.has_status && (
            <SegmentedControl
              size="xs"
              data={[
                { value: '', label: 'Todos' },
                { value: 'published', label: 'Publicados' },
                { value: 'draft', label: 'Borradores' },
              ]}
              value={status}
              onChange={setStatus}
            />
          )}
          <Button onClick={() => setEditing('new')}>Nueva entrada</Button>
        </Group>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay entradas todavía"
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
          onEdit: (entry) => setEditing(entry),
          onDelete: async (entry) => {
            await cmsService.entries(slug).delete(entry.id)
            list.reload()
          },
        }}
      />

      {editing && (
        <EntryFormModal
          slug={slug}
          schema={schema}
          entry={editing === 'new' ? null : editing}
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

/** Celda de listado según el tipo de campo. */
function renderCell(field: FieldSchema, value: unknown): ReactNode {
  if (value === null || value === undefined || value === '') {
    return <Text c="dimmed" size="sm">—</Text>
  }

  switch (field.type) {
    case 'boolean':
      return (
        <Badge size="sm" variant="light" color={value ? 'green' : 'gray'}>
          {value ? 'Sí' : 'No'}
        </Badge>
      )
    case 'media': {
      // En el listado el API adjunta `{campo}_url`; si solo hay ID, lo mostramos como texto.
      const isUrl = /^\/?(https?:\/\/|storage\/|media\/)/.test(String(value))
      return isUrl ? (
        <Image src={String(value)} alt={field.label} h={40} w={56} radius="sm" fit="cover" />
      ) : (
        <Badge size="sm" variant="light" color="gray">media #{String(value)}</Badge>
      )
    }
    case 'color':
      return (
        <Group gap={6}>
          <Box
            w={14}
            h={14}
            style={{ background: String(value), borderRadius: 4, border: '1px solid var(--mantine-color-gray-4)' }}
          />
          <Text size="sm">{String(value)}</Text>
        </Group>
      )
    case 'datetime':
      return <Text size="sm">{formatDateTime(String(value))}</Text>
    case 'select':
      return <Text size="sm">{field.options?.choices?.[String(value)] ?? String(value)}</Text>
    case 'multiselect':
    case 'json':
      return (
        <Text size="sm" lineClamp={1} style={{ maxWidth: 220 }}>
          {JSON.stringify(value)}
        </Text>
      )
    default:
      return (
        <Text size="sm" lineClamp={1} style={{ maxWidth: 280 }}>
          {String(value)}
        </Text>
      )
  }
}

/** Formulario de entrada generado íntegramente desde el esquema. */
function EntryFormModal({
  slug,
  schema,
  entry,
  onClose,
  onSaved,
}: {
  slug: string
  schema: CustomModelSchema
  entry: EntryRecord | null
  onClose: () => void
  onSaved: () => void
}) {
  const queryClient = useQueryClient()
  const [errors, setErrors] = useState<Record<string, string>>({})

  const initialValues = useMemo(() => {
    const values: Record<string, unknown> = {}
    for (const field of schema.fields.filter((f) => f.show_in_form)) {
      const raw = entry?.[field.name]
      values[field.name] =
        raw === null || raw === undefined
          ? field.type === 'boolean'
            ? Boolean(field.default_value)
            : (field.default_value ?? (['multiselect', 'json'].includes(field.type) ? [] : ''))
          : raw
    }
    if (schema.has_status) values.status = entry?.status ?? 'draft'
    return values
  }, [schema, entry])

  const [values, setValues] = useState<Record<string, unknown>>(initialValues)

  const setValue = (name: string, value: unknown) =>
    setValues((current) => ({ ...current, [name]: value }))

  const save = useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const normalized = { ...payload }
      for (const field of schema.fields) {
        // Normaliza cadenas vacías a null (fechas, números…).
        if (normalized[field.name] === '') normalized[field.name] = null
      }
      return entry
        ? cmsService.entries(slug).update(entry.id, normalized)
        : cmsService.entries(slug).create(normalized)
    },
    onSuccess: () => {
      notifications.show({ message: 'Entrada guardada.', color: 'green' })
      void queryClient.invalidateQueries({ queryKey: [`cm-${slug}`] })
      onSaved()
    },
    onError: (error) => {
      const mapped = validationErrors(error)
      setErrors(mapped)
      if (Object.keys(mapped).length === 0) {
        setErrors({ _form: 'No se pudo guardar la entrada.' })
      }
    },
  })

  const submit = () => {
    const missing = schema.fields.find(
      (field) => field.is_required && field.show_in_form && !values[field.name],
    )
    if (missing) {
      setErrors({ [missing.name]: 'Este campo es obligatorio' })
      return
    }
    setErrors({})
    save.mutate(values)
  }

  return (
    <Modal
      opened
      onClose={onClose}
      title={entry ? `Editar #${entry.id}` : `Nueva entrada · ${schema.label}`}
      size="xl"
      centered
    >
      <Stack gap="sm">
        {errors._form && <Text c="red" size="sm">{errors._form}</Text>}

        {schema.fields
          .filter((field) => field.show_in_form)
          .map((field) => (
            <FieldRenderer
              key={field.id}
              field={field}
              value={values[field.name]}
              error={errors[field.name]}
              onChange={(value) => setValue(field.name, value)}
            />
          ))}

        {schema.has_status && (
          <Group gap="sm">
            <Text size="sm" c="dimmed">Estado:</Text>
            <SegmentedControl
              size="xs"
              data={[
                { value: 'draft', label: 'Borrador' },
                { value: 'published', label: 'Publicado' },
                { value: 'archived', label: 'Archivado' },
              ]}
              value={String(values.status ?? 'draft')}
              onChange={(value) => setValue('status', value)}
            />
          </Group>
        )}

        <Group justify="flex-end" mt="sm">
          <Button variant="subtle" color="gray" onClick={onClose}>
            Cancelar
          </Button>
          <Button loading={save.isPending} onClick={submit}>
            Guardar entrada
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
