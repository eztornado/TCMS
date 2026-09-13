import type { ReactNode } from 'react'
import { Box, Button, Card, Group, Stack, Text, Title, Transition } from '@mantine/core'
import { IconArrowLeft, IconDeviceFloppy } from '@tabler/icons-react'
import { useMediaQuery } from '@mantine/hooks'
import { useNavigate } from 'react-router-dom'

interface FormShellProps {
  title: string
  description?: string
  children: ReactNode
  onSubmit: () => void
  saving?: boolean
  /** Texto del botón principal. */
  submitLabel?: string
  disabled?: boolean
  aside?: ReactNode
}

/**
 * Contenedor estándar de formularios del panel: tarjeta principal + barra de
 * acciones flotante (que reserva su propio espacio, como el patrón de rotary
 * pero sin CSS adicional).
 */
export function FormShell({
  title,
  description,
  children,
  onSubmit,
  saving = false,
  submitLabel = 'Guardar',
  disabled = false,
  aside,
}: FormShellProps) {
  const navigate = useNavigate()
  const isMobile = useMediaQuery('(max-width: 62em)')

  return (
    <Box pos="relative">
      <Stack gap="md">
        <Group justify="space-between" align="flex-start" wrap="nowrap">
          <Stack gap={2}>
            <Title order={3}>{title}</Title>
            {description && (
              <Text size="sm" c="dimmed">
                {description}
              </Text>
            )}
          </Stack>
        </Group>

        <Group align="flex-start" gap="md" wrap={isMobile ? 'wrap' : 'nowrap'}>
          <Card withBorder p="lg" style={{ flex: 3, minWidth: 0, width: '100%' }}>
            <form
              onSubmit={(event) => {
                event.preventDefault()
                onSubmit()
              }}
            >
              <Stack gap="md">{children}</Stack>
            </form>
          </Card>

          {aside && (
            <Card withBorder p="lg" w={isMobile ? '100%' : 300} style={{ flexShrink: 0 }}>
              {aside}
            </Card>
          )}
        </Group>

        {/* Reserva de espacio para la barra flotante */}
        <Box h={isMobile ? 76 : 0} />

        <Transition mounted transition="slide-up" duration={200}>
          {(styles) => (
            <Box style={{ ...styles, position: 'fixed', bottom: 16, left: 0, right: 0, zIndex: 199 }}>
              <Group justify="center" gap="xs">
                <Card withBorder radius="xl" px="md" py={8} shadow="md" bg="var(--mantine-color-body)">
                  <Group gap="xs" wrap="nowrap">
                    <Button
                      variant="subtle"
                      color="gray"
                      leftSection={<IconArrowLeft size={16} />}
                      onClick={() => navigate(-1)}
                    >
                      Volver
                    </Button>
                    <Button
                      type="submit"
                      leftSection={<IconDeviceFloppy size={16} />}
                      loading={saving}
                      disabled={disabled}
                      onClick={onSubmit}
                    >
                      {submitLabel}
                    </Button>
                  </Group>
                </Card>
              </Group>
            </Box>
          )}
        </Transition>
      </Stack>
    </Box>
  )
}
