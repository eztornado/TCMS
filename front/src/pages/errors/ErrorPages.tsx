import { Anchor, Center, Code, Group, Stack, Text, Title, Button } from '@mantine/core'
import { useLocation } from 'react-router-dom'
import { IconForbid, IconSearchOff } from '@tabler/icons-react'

export function ForbiddenPage() {
  const location = useLocation()
  const state = location.state as { permission?: string } | null
  return (
    <Center mih="60vh">
      <Stack align="center" gap="sm">
        <IconForbid size={56} stroke={1.2} color="var(--mantine-color-red-6)" />
        <Title order={2}>Acceso denegado</Title>
        <Text c="dimmed" size="sm">
          No tienes permisos para ver esta página.
        </Text>
        {state?.permission && (
          <Code>
            Permiso requerido: {state.permission}
          </Code>
        )}
        <Anchor href="/" size="sm">
          Volver al inicio
        </Anchor>
      </Stack>
    </Center>
  )
}

export function NotFoundPage() {
  return (
    <Center mih="60vh">
      <Stack align="center" gap="sm">
        <IconSearchOff size={56} stroke={1.2} color="var(--mantine-color-dimmed)" />
        <Title order={2}>Página no encontrada</Title>
        <Text c="dimmed" size="sm">
          La página que buscas no existe o fue movida.
        </Text>
        <Group>
          <Button component="a" href="/" variant="light">
            Volver al inicio
          </Button>
        </Group>
      </Stack>
    </Center>
  )
}
