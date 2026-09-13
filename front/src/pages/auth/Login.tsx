import { useEffect } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import {
  Anchor,
  Box,
  Button,
  Card,
  Center,
  Checkbox,
  Group,
  PasswordInput,
  Stack,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import { IconAlertTriangle, IconLogin } from '@tabler/icons-react'
import { useAuthStore } from '@/store/authStore'
import { useLogin } from '@/hooks/useAuth'
import { config } from '@/config'

export default function Login() {
  const location = useLocation()
  const { user } = useAuthStore()
  const login = useLogin()

  useEffect(() => {
    if (new URLSearchParams(location.search).get('expired')) {
      notifications.show({
        title: 'Sesión caducada',
        message: 'Vuelve a iniciar sesión para continuar.',
        color: 'orange',
        icon: <IconAlertTriangle size={16} />,
      })
    }
  }, [location.search])

  const form = useForm({
    mode: 'uncontrolled',
    initialValues: { email: '', password: '', remember: true },
    validate: {
      email: (v) => (/^\S+@\S+$/.test(v) ? null : 'Introduce un email válido'),
      password: (v) => (v ? null : 'Introduce la contraseña'),
    },
  })

  if (user) return <Navigate to="/" replace />

  return (
    <Box
      style={{
        minHeight: '100vh',
        background: 'linear-gradient(135deg, var(--mantine-color-tornado-0), var(--mantine-color-body))',
      }}
    >
      <Center mih="100vh" p="md">
        <Card w={420} radius="lg" p="xl" withBorder>
          <Stack gap="lg">
            <Stack gap={4} ta="center">
              <Title order={1} c="tornado.6" size="2.2rem" fw={800}>
                {config.appShortName}
              </Title>
              <Text c="dimmed" size="sm">
                Panel de gestión · {config.appName}
              </Text>
            </Stack>

            <form onSubmit={form.onSubmit((values) => login.mutate(values))}>
              <Stack gap="sm">
                <TextInput
                  label="Email"
                  placeholder="tu@email.com"
                  autoComplete="email"
                  key={form.key('email')}
                  {...form.getInputProps('email')}
                />
                <PasswordInput
                  label="Contraseña"
                  placeholder="••••••••"
                  autoComplete="current-password"
                  key={form.key('password')}
                  {...form.getInputProps('password')}
                />
                <Group justify="space-between">
                  <Checkbox
                    label="Mantener sesión"
                    key={form.key('remember')}
                    {...form.getInputProps('remember')}
                  />
                  <Anchor size="sm" href="/recuperar-contrasena" c="dimmed">
                    ¿La has olvidado?
                  </Anchor>
                </Group>
                <Button
                  type="submit"
                  fullWidth
                  leftSection={<IconLogin size={16} />}
                  loading={login.isPending}
                  mt="xs"
                >
                  Entrar
                </Button>
              </Stack>
            </form>
          </Stack>
        </Card>
      </Center>
    </Box>
  )
}
