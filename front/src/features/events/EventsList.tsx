import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  ActionIcon,
  Badge,
  Button,
  Divider,
  Group,
  Modal,
  NumberInput,
  Stack,
  Switch,
  Text,
  TextInput,
  Textarea,
  Tooltip,
} from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import {
  IconCalendarPlus,
  IconPlus,
  IconTrash,
  IconUsers,
} from '@tabler/icons-react'
import { DataTable, type DataColumn } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import { eventsService, type EventRecord, type EventSession } from '@/features/events/eventsService'
import { formatDateTime, formatMoney } from '@/lib/format'
import { validationErrors } from '@/lib/api'

const STATUS_COLORS: Record<string, string> = {
  published: 'green',
  draft: 'gray',
  archived: 'orange',
}

/** Gestión de eventos de ocio con sus sesiones (pases) y aforo. */
export default function EventsList() {
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<EventRecord | 'new' | null>(null)
  const list = useListQuery<EventRecord>({ resource: 'events', endpoint: '/admin/events', search })

  const columns: DataColumn<EventRecord>[] = [
    { key: 'title', label: 'Evento', sortable: true, render: (event) => (
      <Stack gap={0}>
        <Text fw={500}>{event.title}</Text>
        <Text size="xs" c="dimmed">{event.venue ?? '—'}{event.city ? ` · ${event.city}` : ''}</Text>
      </Stack>
    ) },
    { key: 'price_cents', label: 'Precio', sortable: true, render: (event) => (event.price_cents === null ? 'Gratis' : formatMoney(event.price_cents / 100)) },
    {
      key: 'sessions',
      label: 'Sesiones',
      render: (event) => (
        <Group gap={4}>
          <Badge size="sm" variant="light" leftSection={<IconCalendarPlus size={10} />}>
            {event.sessions?.length ?? 0}
          </Badge>
          {(event.sessions ?? []).slice(0, 1).map((session) => (
            <Text key={session.id} size="xs" c="dimmed">{formatDateTime(session.starts_at)}</Text>
          ))}
        </Group>
      ),
      hideOnMobile: true,
    },
    { key: 'capacity', label: 'Plazas libres', render: (event) => (
      <Group gap={4} wrap="nowrap">
        <IconUsers size={14} />
        <Text size="sm">{event.seats_left ?? '—'}</Text>
      </Group>
    ), hideOnMobile: true },
    { key: 'status', label: 'Estado', sortable: true, render: (event) => (
      <Badge size="sm" variant="light" color={STATUS_COLORS[event.status] ?? 'gray'}>{event.status}</Badge>
    ) },
    { key: 'published_at', label: 'Publicado', sortable: true, hideOnMobile: true, render: (event) => formatDateTime(event.published_at) },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <TextInput
          placeholder="Buscar evento…"
          value={search}
          onChange={(e) => setSearch(e.currentTarget.value)}
          onKeyDown={(e) => e.key === 'Enter' && list.setSearch(search)}
          w={300}
        />
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
          Nuevo evento
        </Button>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay eventos"
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
          onEdit: (event) => setEditing(event),
          onDelete: async (event) => {
            await eventsService.delete(event.id)
            list.reload()
          },
        }}
      />

      {editing && (
        <EventFormModal
          event={editing === 'new' ? null : editing}
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

function EventFormModal({
  event,
  onClose,
  onSaved,
}: {
  event: EventRecord | null
  onClose: () => void
  onSaved: () => void
}) {
  const queryClient = useQueryClient()

  const form = useForm<{
    title: string
    slug: string
    excerpt: string
    description: string
    venue: string
    city: string
    capacity: number | null
    price: number | null
    is_featured: boolean
    status: string
    sessions: EventSession[]
  }>({
    mode: 'uncontrolled',
    initialValues: {
      title: event?.title ?? '',
      slug: event?.slug ?? '',
      excerpt: event?.excerpt ?? '',
      description: event?.description ?? '',
      venue: event?.venue ?? '',
      city: event?.city ?? '',
      capacity: event?.capacity ?? null,
      price: event?.price_cents ? event.price_cents / 100 : null,
      is_featured: event?.is_featured ?? false,
      status: event?.status ?? 'draft',
      sessions: event?.sessions ?? [],
    },
    validate: {
      title: (v) => (v.trim().length >= 3 ? null : 'Indica el título'),
      price: (v) => (v === null || v >= 0 ? null : 'El precio no puede ser negativo'),
    },
  })

  const save = useMutation({
    mutationFn: (values: typeof form.values) =>
      eventsService.saveWithSessions({
        ...(event ? { id: event.id } : {}),
        slug: values.slug || undefined,
        title: values.title,
        excerpt: values.excerpt,
        description: values.description,
        venue: values.venue,
        city: values.city,
        capacity: values.capacity,
        price_cents: values.price !== null ? Math.round(values.price * 100) : null,
        is_featured: values.is_featured,
        status: values.status,
        sessions: values.sessions.map((session) => ({
          ...session,
          price_cents: session.price_cents,
        })),
      }),
    onSuccess: () => {
      notifications.show({ message: 'Evento guardado.', color: 'green' })
      void queryClient.invalidateQueries({ queryKey: ['events'] })
      onSaved()
    },
    onError: (error) => {
      const errors = validationErrors(error)
      Object.entries(errors).forEach(([field, message]) => form.setFieldError(field, message))
    },
  })

  const addSession = () => {
    form.insertListItem('sessions', {
      title: '',
      starts_at: null,
      ends_at: null,
      capacity: null,
      price_cents: null,
      status: 'scheduled',
    } satisfies EventSession)
  }

  return (
    <Modal opened onClose={onClose} title={event ? `Evento: ${event.title}` : 'Nuevo evento'} size="72rem" centered>
      <form onSubmit={form.onSubmit((values) => save.mutate(values))}>
        <Stack gap="sm">
          <Group grow>
            <TextInput label="Título" key={form.key('title')} {...form.getInputProps('title')} />
            <TextInput label="Slug (opcional)" key={form.key('slug')} {...form.getInputProps('slug')} />
          </Group>
          <Group grow>
            <TextInput label="Recinto" key={form.key('venue')} {...form.getInputProps('venue')} />
            <TextInput label="Ciudad" key={form.key('city')} {...form.getInputProps('city')} />
          </Group>
          <Group grow>
            <NumberInput
              label="Aforo por defecto"
              min={0}
              key={form.key('capacity')}
              {...form.getInputProps('capacity')}
            />
            <NumberInput
              label="Precio (0 = gratuito)"
              min={0}
              decimalScale={2}
              decimalSeparator=","
              key={form.key('price')}
              {...form.getInputProps('price')}
            />
            <Switch mt="lg" label="Destacado" key={form.key('is_featured')} {...form.getInputProps('is_featured', { type: 'checkbox' })} />
          </Group>
          <Textarea label="Resumen" autosize minRows={2} key={form.key('excerpt')} {...form.getInputProps('excerpt')} />
          <Textarea
            label="Descripción (HTML)"
            autosize
            minRows={4}
            styles={{ input: { fontFamily: 'monospace', fontSize: 13 } }}
            key={form.key('description')}
            {...form.getInputProps('description')}
          />

          <Divider label="Sesiones (pases)" labelPosition="center" />

          <Stack gap="xs">
            {form.values.sessions.map((session, index) => (
              <Group key={form.key(`sessions.${index}.starts_at`) ?? index} align="flex-end" gap="xs" wrap="nowrap">
                <TextInput
                  placeholder="Nombre del pase (opcional)"
                  w={180}
                  key={form.key(`sessions.${index}.title`)}
                  {...form.getInputProps(`sessions.${index}.title`)}
                />
                <DateTimePicker
                  placeholder="Empieza"
                  w={220}
                  valueFormat="DD/MM/YYYY HH:mm"
                  clearable
                  key={form.key(`sessions.${index}.starts_at`)}
                  {...form.getInputProps(`sessions.${index}.starts_at`)}
                />
                <NumberInput
                  placeholder="Aforo"
                  w={100}
                  min={0}
                  key={form.key(`sessions.${index}.capacity`)}
                  {...form.getInputProps(`sessions.${index}.capacity`)}
                />
                <NumberInput
                  placeholder="Precio €"
                  w={110}
                  min={0}
                  decimalScale={2}
                  key={form.key(`sessions.${index}.price_cents`)}
                  value={session.price_cents ? session.price_cents / 100 : undefined}
                  onChange={(value) =>
                    form.setFieldValue(
                      `sessions.${index}.price_cents`,
                      value ? Math.round(Number(value) * 100) : null,
                    )
                  }
                />
                <Tooltip label="Eliminar sesión">
                  <ActionIcon
                    color="red"
                    variant="subtle"
                    mb={6}
                    onClick={() => form.removeListItem('sessions', index)}
                    aria-label="Eliminar sesión"
                  >
                    <IconTrash size={16} />
                  </ActionIcon>
                </Tooltip>
              </Group>
            ))}
            <Button
              variant="light"
              size="compact-sm"
              leftSection={<IconPlus size={14} />}
              onClick={addSession}
              w="fit-content"
            >
              Añadir sesión
            </Button>
          </Stack>

          <Group justify="flex-end" mt="sm">
            <Button variant="subtle" color="gray" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={save.isPending}>
              Guardar evento
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
