import { useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import {
  ActionIcon,
  Badge,
  Box,
  Button,
  Card,
  Checkbox,
  Group,
  Loader,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
  Title,
  Tooltip,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import {
  IconArrowDown,
  IconArrowUp,
  IconDeviceFloppy,
  IconPlus,
  IconTrash,
} from '@tabler/icons-react'
import { cmsService } from '@/services/cmsService'
import { errorMessage } from '@/lib/api'
import type { FieldSchema } from '@/types/schema'

const FIELD_TYPES: Array<{ value: FieldSchema['type']; label: string }> = [
  { value: 'text', label: 'Texto' },
  { value: 'textarea', label: 'Texto largo' },
  { value: 'richtext', label: 'Texto enriquecido (HTML)' },
  { value: 'number', label: 'Número' },
  { value: 'decimal', label: 'Decimal' },
  { value: 'boolean', label: 'Sí/No' },
  { value: 'date', label: 'Fecha' },
  { value: 'datetime', label: 'Fecha y hora' },
  { value: 'select', label: 'Selección (opciones)' },
  { value: 'multiselect', label: 'Selección múltiple' },
  { value: 'media', label: 'Imagen' },
  { value: 'relation', label: 'Relación con otro modelo' },
  { value: 'slug', label: 'Slug (URL)' },
  { value: 'color', label: 'Color' },
  { value: 'json', label: 'JSON' },
]

interface BuilderField {
  id?: number
  name: string
  label: string
  type: FieldSchema['type']
  options_json: string
  is_required: boolean
  is_unique: boolean
  is_searchable: boolean
  is_filterable: boolean
  show_in_list: boolean
  show_in_form: boolean
}

const emptyField = (): BuilderField => ({
  name: '',
  label: '',
  type: 'text',
  options_json: '',
  is_required: false,
  is_unique: false,
  is_searchable: false,
  is_filterable: false,
  show_in_list: true,
  show_in_form: true,
})

/** Constructor visual del esquema de un custom model. */
export default function CustomModelBuilder() {
  const { slug = '' } = useParams()
  const queryClient = useQueryClient()

  const { data: schema, isLoading } = useQuery({
    queryKey: ['cm-schema', slug],
    queryFn: () => cmsService.schema(slug),
    enabled: Boolean(slug),
  })

  const form = useForm<{ fields: BuilderField[] }>({
    mode: 'uncontrolled',
    initialValues: { fields: [] },
  })

  useEffect(() => {
    if (schema) {
      form.setValues({
        fields: schema.fields.map((field) => ({
          id: field.id,
          name: field.name,
          label: field.label,
          type: field.type,
          options_json: field.options ? JSON.stringify(field.options) : '',
          is_required: field.is_required,
          is_unique: field.is_unique,
          is_searchable: field.is_searchable,
          is_filterable: field.is_filterable,
          show_in_list: field.show_in_list,
          show_in_form: field.show_in_form,
        })),
      })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [schema])

  const save = useMutation({
    mutationFn: (values: { fields: BuilderField[] }) => {
      const previousIds = (schema?.fields ?? []).map((field) => field.id)
      return cmsService.saveFields(
        slug,
        values.fields.map((field, index) => ({
          id: field.id,
          name: field.name,
          label: field.label,
          type: field.type,
          sort: index,
          options: field.options_json ? (safeJson(field.options_json) as FieldSchema['options']) : null,
          is_required: field.is_required,
          is_unique: field.is_unique,
          is_searchable: field.is_searchable,
          is_filterable: field.is_filterable,
          show_in_list: field.show_in_list,
          show_in_form: field.show_in_form,
        })),
        previousIds,
      )
    },
    onSuccess: () => {
      notifications.show({
        title: 'Esquema guardado',
        message: 'Los campos se han sincronizado con la tabla.',
        color: 'green',
      })
      void queryClient.invalidateQueries({ queryKey: ['cm-schema', slug] })
      void queryClient.invalidateQueries({ queryKey: ['custom-models'] })
    },
    onError: (error) => notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' }),
  })

  if (isLoading || !schema) {
    return <Loader size="sm" />
  }

  const fields = form.values.fields

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <Stack gap={2}>
          <Title order={3}>Esquema: {schema.label}</Title>
          <Group gap={6}>
            <Badge variant="light" color="gray" ff="monospace">
              /api/cm/{schema.slug}
            </Badge>
            <Badge variant="light">{fields.length} campos</Badge>
            <Button component="a" href={`/content/${slug}/entries`} variant="subtle" size="compact-sm">
              Ver entradas →
            </Button>
          </Group>
        </Stack>
        <Group gap="xs">
          <Button
            variant="light"
            leftSection={<IconPlus size={16} />}
            onClick={() => form.insertListItem('fields', emptyField())}
          >
            Añadir campo
          </Button>
          <Button
            leftSection={<IconDeviceFloppy size={16} />}
            loading={save.isPending}
            onClick={() => save.mutate(form.values)}
          >
            Guardar esquema
          </Button>
        </Group>
      </Group>

      <Stack gap="sm">
        {fields.length === 0 && (
          <Card withBorder p="xl">
            <Text c="dimmed" ta="center">
              Este modelo todavía no tiene campos. Añade el primero: p. ej. «title» (texto,
              requerido, buscable).
            </Text>
          </Card>
        )}

        {fields.map((_field, index) => (
          <Card key={form.key(`fields.${index}.id`) ?? index} withBorder p="sm">
            <Group align="flex-start" gap="sm" wrap="nowrap">
              <Stack gap={4} style={{ flexShrink: 0 }}>
                <Tooltip label="Subir">
                  <ActionIcon
                    variant="subtle"
                    disabled={index === 0}
                    onClick={() => form.reorderListItem('fields', { from: index, to: index - 1 })}
                    aria-label="Subir campo"
                  >
                    <IconArrowUp size={16} />
                  </ActionIcon>
                </Tooltip>
                <Tooltip label="Bajar">
                  <ActionIcon
                    variant="subtle"
                    disabled={index === fields.length - 1}
                    onClick={() => form.reorderListItem('fields', { from: index, to: index + 1 })}
                    aria-label="Bajar campo"
                  >
                    <IconArrowDown size={16} />
                  </ActionIcon>
                </Tooltip>
                <Tooltip label="Eliminar campo">
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    onClick={() => form.removeListItem('fields', index)}
                    aria-label="Eliminar campo"
                  >
                    <IconTrash size={16} />
                  </ActionIcon>
                </Tooltip>
              </Stack>

              <SimpleGrid cols={{ base: 1, md: 4 }} style={{ flex: 1, minWidth: 0 }}>
                <TextInput
                  label="Identificador"
                  placeholder="titulo"
                  size="sm"
                  key={form.key(`fields.${index}.name`)}
                  {...form.getInputProps(`fields.${index}.name`)}
                />
                <TextInput
                  label="Etiqueta"
                  placeholder="Título"
                  size="sm"
                  key={form.key(`fields.${index}.label`)}
                  {...form.getInputProps(`fields.${index}.label`)}
                />
                <Select
                  label="Tipo"
                  size="sm"
                  allowDeselect={false}
                  data={FIELD_TYPES}
                  key={form.key(`fields.${index}.type`)}
                  {...form.getInputProps(`fields.${index}.type`)}
                />
                <TextInput
                  label="Opciones (JSON)"
                  placeholder='{"choices": {"a": "Opción A"}}'
                  size="sm"
                  key={form.key(`fields.${index}.options_json`)}
                  {...form.getInputProps(`fields.${index}.options_json`)}
                />
              </SimpleGrid>

              <Group gap="xs" mt="lg" style={{ flexShrink: 0 }} wrap="nowrap">
                <Tooltip label="Requerido">
                  <Box>
                    <Checkbox
                      label="Req."
                      size="sm"
                      key={form.key(`fields.${index}.is_required`)}
                      {...form.getInputProps(`fields.${index}.is_required`, { type: 'checkbox' })}
                    />
                  </Box>
                </Tooltip>
                <Tooltip label="Único">
                  <Box>
                    <Checkbox
                      label="Ún."
                      size="sm"
                      key={form.key(`fields.${index}.is_unique`)}
                      {...form.getInputProps(`fields.${index}.is_unique`, { type: 'checkbox' })}
                    />
                  </Box>
                </Tooltip>
                <Tooltip label="Buscable">
                  <Box>
                    <Checkbox
                      label="Búsq."
                      size="sm"
                      key={form.key(`fields.${index}.is_searchable`)}
                      {...form.getInputProps(`fields.${index}.is_searchable`, { type: 'checkbox' })}
                    />
                  </Box>
                </Tooltip>
                <Tooltip label="Ver en listado">
                  <Box>
                    <Checkbox
                      label="Lista"
                      size="sm"
                      key={form.key(`fields.${index}.show_in_list`)}
                      {...form.getInputProps(`fields.${index}.show_in_list`, { type: 'checkbox' })}
                    />
                  </Box>
                </Tooltip>
              </Group>
            </Group>
          </Card>
        ))}
      </Stack>
    </Stack>
  )
}

function safeJson(value: string): unknown {
  try {
    return JSON.parse(value)
  } catch {
    notifications.show({
      title: 'JSON no válido',
      message: `«${value}» no es JSON válido; se ignoran esas opciones.`,
      color: 'orange',
    })
    return null
  }
}
