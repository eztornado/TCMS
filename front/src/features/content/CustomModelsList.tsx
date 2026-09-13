import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  ActionIcon,
  Badge,
  Button,
  Card,
  Group,
  LoadingOverlay,
  Modal,
  SimpleGrid,
  Stack,
  Switch,
  Text,
  TextInput,
  Title,
  Tooltip,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import { modals } from '@mantine/modals'
import {
  IconArticle,
  IconDatabase,
  IconPencil,
  IconPlus,
  IconListNumbers,
  IconTrash,
} from '@tabler/icons-react'
import { cmsService } from '@/services/cmsService'
import { errorMessage } from '@/lib/api'
import { slugify } from '@/components/fields/FieldRenderer'
import type { CustomModelDefinition } from '@/types/schema'

/** Índice de tipos de contenido dinámico definidos en el CMS. */
export default function CustomModelsList() {
  const queryClient = useQueryClient()
  const [builderOpened, { open: openBuilder, close: closeBuilder }] = useDisclosure(false)

  const { data: models = [], isLoading } = useQuery({ queryKey: ['custom-models'], queryFn: cmsService.models })

  const remove = (model: CustomModelDefinition) => {
    modals.openConfirmModal({
      title: `Eliminar «${model.label}»`,
      children: (
        <Text size="sm">
          Se eliminarán también la tabla <code>{model.table_name}</code> y todas sus entradas. Esta
          acción no se puede deshacer.
        </Text>
      ),
      labels: { confirm: 'Eliminar todo', cancel: 'Cancelar' },
      confirmProps: { color: 'red' },
      onConfirm: async () => {
        try {
          await cmsService.deleteModel(model.slug)
          notifications.show({ message: 'Modelo eliminado.', color: 'green' })
          void queryClient.invalidateQueries({ queryKey: ['custom-models'] })
        } catch (error) {
          notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' })
        }
      },
    })
  }

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Stack gap={2}>
          <Title order={3}>Tipos de contenido</Title>
          <Text size="sm" c="dimmed">
            Crea estructuras de contenido a medida (como los «custom post types» de WordPress) sin
            escribir código: cada modelo tiene su tabla, sus campos y su CRUD.
          </Text>
        </Stack>
        <Button leftSection={<IconPlus size={16} />} onClick={openBuilder}>
          Nuevo tipo de contenido
        </Button>
      </Group>

      <LoadingOverlay visible={isLoading} />

      <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
        {models.map((model) => (
          <Card key={model.id} withBorder p="lg" style={{ display: 'flex', flexDirection: 'column' }}>
            <Group justify="space-between" mb="xs" wrap="nowrap">
              <Group gap="xs" wrap="nowrap">
                <IconArticle size={20} color="var(--mantine-color-tornado-6)" />
                <Stack gap={0}>
                  <Text fw={600} lh={1.2}>
                    {model.label}
                  </Text>
                  <Text size="xs" c="dimmed" ff="monospace">
                    {model.slug}
                  </Text>
                </Stack>
              </Group>
              <Group gap={4}>
                <Tooltip label="Editar campos">
                  <ActionIcon component="a" href={`/content/${model.slug}/edit`} aria-label="Editar campos">
                    <IconPencil size={16} />
                  </ActionIcon>
                </Tooltip>
                <Tooltip label="Eliminar">
                  <ActionIcon color="red" onClick={() => remove(model)} aria-label="Eliminar modelo">
                    <IconTrash size={16} />
                  </ActionIcon>
                </Tooltip>
              </Group>
            </Group>

            <Group gap={6} mb="md">
              {model.has_status && <Badge size="sm" variant="light" color="blue">estados</Badge>}
              {model.is_taxonomizable && <Badge size="sm" variant="light" color="grape">taxonomías</Badge>}
              <Badge size="sm" variant="light" color="gray" leftSection={<IconDatabase size={10} />}>
                {model.table_name}
              </Badge>
            </Group>

            <Button
              mt="auto"
              variant="light"
              leftSection={<IconListNumbers size={16} />}
              component="a"
              href={`/content/${model.slug}/entries`}
            >
              Ver entradas
            </Button>
          </Card>
        ))}
      </SimpleGrid>

      <CreateModelModal opened={builderOpened} onClose={closeBuilder} />
    </Stack>
  )
}

function CreateModelModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const form = useForm({
    mode: 'uncontrolled',
    initialValues: {
      label: '',
      slug: '',
      plural_label: '',
      has_status: true,
      is_taxonomizable: false,
    },
    validate: {
      label: (v) => (v.trim().length >= 2 ? null : 'Indica un nombre'),
      slug: (v) => (/^[a-z][a-z0-9-]{1,40}$/.test(v) ? null : 'Minúsculas, números y guiones'),
    },
  })

  const create = useMutation({
    mutationFn: (values: { slug: string; label: string; plural_label: string; has_status: boolean; is_taxonomizable: boolean }) =>
      cmsService.createModel(values),
    onSuccess: (model) => {
      notifications.show({
        title: 'Modelo creado',
        message: `Se ha creado la tabla para «${model.label}». Añade ahora sus campos.`,
        color: 'green',
      })
      void queryClient.invalidateQueries({ queryKey: ['custom-models'] })
      window.location.href = `/content/${model.slug}/edit`
    },
    onError: (error) => notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' }),
  })

  return (
    <Modal opened={opened} onClose={onClose} title="Nuevo tipo de contenido" centered>
      <form onSubmit={form.onSubmit((values) => create.mutate(values))}>
        <Stack gap="sm">
          <TextInput
            label="Nombre (singular)"
            placeholder="p. ej. Testimonio"
            key={form.key('label')}
            {...form.getInputProps('label')}
            onChange={(event) => {
              const label = event.currentTarget.value
              form.setFieldValue('label', label)
              if (!form.isTouched('slug')) form.setFieldValue('slug', slugify(label))
            }}
          />
          <TextInput
            label="Identificador (slug)"
            placeholder="testimonios"
            description="Se usará en la URL de la API: /cm/{slug}"
            key={form.key('slug')}
            {...form.getInputProps('slug')}
          />
          <TextInput
            label="Nombre (plural)"
            placeholder="Testimonios"
            key={form.key('plural_label')}
            {...form.getInputProps('plural_label')}
          />
          <Switch label="Con estado editorial (borrador/publicado)" key={form.key('has_status')} {...form.getInputProps('has_status', { type: 'checkbox' })} />
          <Switch label="Con categorías y etiquetas" key={form.key('is_taxonomizable')} {...form.getInputProps('is_taxonomizable', { type: 'checkbox' })} />
          <Group justify="flex-end" mt="sm">
            <Button variant="subtle" color="gray" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={create.isPending}>
              Crear modelo
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
