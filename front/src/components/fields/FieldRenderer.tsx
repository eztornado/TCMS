import { useState, type ReactNode } from 'react'
import {
  ColorInput,
  FileButton,
  Image,
  JsonInput,
  MultiSelect,
  NumberInput,
  PasswordInput,
  Select,
  Switch,
  Textarea,
  TextInput,
  Tooltip,
  ActionIcon,
  Group,
} from '@mantine/core'
import { DatePickerInput, DateTimePicker } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { IconUpload } from '@tabler/icons-react'
import { api } from '@/lib/api'
import { config } from '@/config'
import type { FieldSchema } from '@/types/schema'

interface FieldWidgetProps {
  field: FieldSchema
  value: unknown
  error?: string
  disabled?: boolean
  onChange: (value: unknown) => void
}

/**
 * Widget Mantine adecuado para un campo del esquema de un custom model.
 * Es un componente controlado simple (value/onChange): no acopla el motor de
 * contenido a ninguna librería de formularios.
 */
export function FieldRenderer({ field, value, error, disabled = false, onChange }: FieldWidgetProps) {
  const commonProps = {
    label: field.label,
    error,
    withAsterisk: field.is_required,
    disabled,
  }

  switch (field.type) {
    case 'textarea':
      return <Textarea autosize minRows={3} maxRows={10} {...commonProps} value={String(value ?? '')} onChange={(e) => onChange(e.currentTarget.value)} />

    case 'richtext':
      return (
        <Textarea
          {...commonProps}
          description="HTML permitido (rich text)"
          autosize
          minRows={6}
          maxRows={20}
          styles={{ input: { fontFamily: 'monospace', fontSize: 13 } }}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.currentTarget.value)}
        />
      )

    case 'number':
      return <NumberInput {...commonProps} value={Number(value ?? 0)} onChange={onChange} />

    case 'decimal':
      return (
        <NumberInput
          {...commonProps}
          decimalSeparator=","
          fixedDecimalScale
          decimalScale={2}
          value={Number(value ?? 0)}
          onChange={onChange}
        />
      )

    case 'boolean':
      return (
        <Switch
          mt="lg"
          label={field.is_required ? `${field.label} *` : field.label}
          error={error}
          disabled={disabled}
          checked={Boolean(value)}
          onChange={(checked) => onChange(checked)}
        />
      )

    case 'date':
      return (
        <DatePickerInput
          {...commonProps}
          clearable
          valueFormat="DD/MM/YYYY"
          locale="es"
          value={value ? new Date(String(value)) : null}
          onChange={(date) => onChange(date)}
        />
      )

    case 'datetime':
      return (
        <DateTimePicker
          {...commonProps}
          clearable
          valueFormat="DD/MM/YYYY HH:mm"
          locale="es"
          value={value ? new Date(String(value)) : null}
          onChange={(date) => onChange(date)}
        />
      )

    case 'select': {
      const choices = Object.entries(field.options?.choices ?? {})
      return (
        <Select
          {...commonProps}
          clearable
          searchable
          data={choices.map(([optionValue, label]) => ({ value: optionValue, label }))}
          value={value ? String(value) : null}
          onChange={onChange}
        />
      )
    }

    case 'multiselect': {
      const choices = Object.entries(field.options?.choices ?? {})
      return (
        <MultiSelect
          {...commonProps}
          data={choices.map(([optionValue, label]) => ({ value: optionValue, label }))}
          value={Array.isArray(value) ? value.map(String) : []}
          onChange={onChange}
        />
      )
    }

    case 'relation':
      return <RelationInput field={field} value={value} error={error} disabled={disabled} onChange={onChange} />

    case 'media':
      return <MediaInput field={field} value={value} error={error} onChange={onChange} />

    case 'slug':
      return (
        <TextInput
          {...commonProps}
          description="Se genera desde el título; admite a-z, 0-9 y guiones"
          leftSection="/"
          value={String(value ?? '')}
          onChange={(e) => onChange(slugify(e.currentTarget.value))}
        />
      )

    case 'color':
      return <ColorInput {...commonProps} value={String(value ?? '#000000')} onChange={onChange} />

    case 'json':
      return (
        <JsonInput
          {...commonProps}
          autosize
          minRows={4}
          formatOnBlur
          value={typeof value === 'string' ? value : JSON.stringify(value ?? null, null, 2)}
          onChange={onChange}
        />
      )

    default:
      if (/password|secret|token/i.test(field.name)) {
        return <SecretInput label={field.label} error={error} value={String(value ?? '')} disabled={disabled} onChange={onChange} />
      }
      return (
        <TextInput
          {...commonProps}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.currentTarget.value)}
        />
      )
  }
}

/** Input de contraseña con toggle de visibilidad. */
function SecretInput({
  label,
  error,
  value,
  disabled,
  onChange,
}: {
  label: ReactNode
  error?: string
  value: string
  disabled?: boolean
  onChange: (value: string) => void
}) {
  const [visible, setVisible] = useState(false)
  return (
    <PasswordInput
      label={label}
      error={error}
      disabled={disabled}
      value={value}
      onChange={(e) => onChange(e.currentTarget.value)}
      visibilityToggleIcon={() => (
        <ActionIcon variant="subtle" onClick={() => setVisible((v) => !v)} aria-label="Mostrar contraseña">
          {visible ? 'Ocultar' : 'Ver'}
        </ActionIcon>
      )}
    />
  )
}

export function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')
    .slice(0, 120)
}

/** Relación: selector con entradas del modelo destino (opción `related`). */
function RelationInput({
  field,
  value,
  error,
  disabled,
  onChange,
}: FieldWidgetProps) {
  const related = String(field.options?.related ?? '')

  const { data: options } = useQuery({
    queryKey: ['cm-options', related],
    enabled: Boolean(related),
    staleTime: 60_000,
    queryFn: () => fetchRelatedOptions(related),
  })

  return (
    <Select
      label={field.label}
      error={error}
      withAsterisk={field.is_required}
      clearable
      searchable
      disabled={disabled}
      data={options ?? []}
      value={value ? String(value) : null}
      onChange={onChange}
    />
  )
}

async function fetchRelatedOptions(slug: string): Promise<Array<{ value: string; label: string }>> {
  try {
    const schema = await api.get<{ data: { show_field: string } }>(`/cm/${slug}/schema`)
    const showField = schema.data.show_field || 'id'
    const page = await api.get<{ data: { data: Array<Record<string, unknown>> } }>(
      `/cm/${slug}?per_page=100`,
    )
    return page.data.data.map((entry) => ({
      value: String(entry.id),
      label: `#${entry.id} · ${String(entry[showField] ?? '')}`,
    }))
  } catch {
    return []
  }
}

/**
 * Imagen: el campo guarda el ID en la biblioteca de medios (contrato del
 * backend) y el preview se resuelve con `{campo}_url` que adjunta el API.
 */
function MediaInput({
  field,
  value,
  error,
  onChange,
}: FieldWidgetProps) {
  const [uploading, setUploading] = useState(false)
  const mediaId = value ? String(value) : ''
  const [previewUrl, setPreviewUrl] = useState('')

  const upload = async (file: File | null) => {
    if (!file) return
    setUploading(true)
    try {
      const data = new FormData()
      data.append('file', file)
      const response = await api.upload<{ data: { id: number; url: string } }>('/admin/media', data)
      setPreviewUrl(response.data.url)
      onChange(response.data.id)
    } finally {
      setUploading(false)
    }
  }

  return (
    <Group align="flex-end" gap="sm" wrap="nowrap" w="100%">
      {(previewUrl || mediaId) && (
        <Tooltip label={`Media #${mediaId}`}>
          <Image src={previewUrl || undefined} alt={field.label} w={72} h={72} radius="md" fit="cover" />
        </Tooltip>
      )}
      <TextInput
        label={`${field.label} (media ID)`}
        error={error}
        placeholder="Sube una imagen desde el botón"
        style={{ flex: 1 }}
        value={mediaId}
        disabled
        onChange={() => undefined}
      />
      <FileButton onChange={upload} accept={config.allowedImageTypes.join(',')}>
        {(props) => (
          <ActionIcon
            {...props}
            loading={uploading}
            variant="light"
            size="lg"
            aria-label="Subir imagen"
          >
            <IconUpload size={18} />
          </ActionIcon>
        )}
      </FileButton>
    </Group>
  )
}
