import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import {
  Button,
  Card,
  Group,
  Loader,
  NumberInput,
  PasswordInput,
  SimpleGrid,
  Stack,
  Switch,
  Textarea,
  TextInput,
  Title,
  Text,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { settingsService } from '@/services/adminService'
import { errorMessage } from '@/lib/api'

interface SettingRow {
  id: number
  group: string
  key: string
  value: unknown
  type: string
  label?: string
}

const GROUP_LABELS: Record<string, string> = {
  general: 'General',
  branding: 'Marca',
  shop: 'Tienda',
  events: 'Eventos',
  seo: 'SEO',
}

/** Ajustes del sitio agrupados por sección; se guardan en bloque. */
export default function SettingsPage() {
  const [values, setValues] = useState<Record<string, unknown>>({})

  const { data, isLoading } = useQuery({ queryKey: ['settings'], queryFn: settingsService.all })

  useEffect(() => {
    if (data) {
      setValues(Object.fromEntries(data.map((setting) => [setting.key, setting.value])))
    }
  }, [data])

  const save = useMutation({
    mutationFn: () => settingsService.save(values),
    onSuccess: () => notifications.show({ message: 'Ajustes guardados.', color: 'green' }),
    onError: (error) =>
      notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' }),
  })

  const grouped = useMemo(() => {
    const groups: Record<string, SettingRow[]> = {}
    for (const setting of data ?? []) {
      groups[setting.group] = groups[setting.group] ?? []
      groups[setting.group].push(setting)
    }
    return groups
  }, [data])

  const setValue = (key: string, value: unknown) =>
    setValues((current) => ({ ...current, [key]: value }))

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Title order={3}>Ajustes</Title>
        <Button onClick={() => save.mutate()} loading={save.isPending} disabled={isLoading}>
          Guardar cambios
        </Button>
      </Group>

      {isLoading ? (
        <Loader size="sm" />
      ) : (
        <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
          {Object.entries(grouped).map(([group, settings]) => (
            <Card withBorder p="lg" key={group}>
              <Stack gap="sm">
                <Text fw={600}>{GROUP_LABELS[group] ?? group}</Text>
                {settings.map((setting) => (
                  <SettingInput
                    key={setting.id}
                    setting={setting}
                    value={values[setting.key]}
                    onChange={(value) => setValue(setting.key, value)}
                  />
                ))}
              </Stack>
            </Card>
          ))}
        </SimpleGrid>
      )}
    </Stack>
  )
}

function SettingInput({
  setting,
  value,
  onChange,
}: {
  setting: SettingRow
  value: unknown
  onChange: (value: unknown) => void
}) {
  const label = setting.label ?? setting.key.replaceAll('_', ' ')

  switch (setting.type) {
    case 'boolean':
      return <Switch label={label} checked={Boolean(value)} onChange={(e) => onChange(e.currentTarget.checked)} />
    case 'number':
      return (
        <NumberInput label={label} value={Number(value ?? 0)} onChange={(v) => onChange(v)} />
      )
    case 'password':
      return (
        <PasswordInput
          label={label}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.currentTarget.value)}
        />
      )
    case 'textarea':
      return (
        <Textarea
          label={label}
          autosize
          minRows={2}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.currentTarget.value)}
        />
      )
    default:
      return (
        <TextInput
          label={label}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.currentTarget.value)}
        />
      )
  }
}
