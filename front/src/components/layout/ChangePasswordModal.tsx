import { useState } from 'react'
import { Button, PasswordInput, Stack } from '@mantine/core'
import { useForm } from '@mantine/form'
import { notifications } from '@mantine/notifications'
import { authService } from '@/services/authService'
import { validationErrors } from '@/lib/api'

export default function ChangePasswordModal({ onDone }: { onDone: () => void }) {
  const [loading, setLoading] = useState(false)
  const form = useForm({
    mode: 'uncontrolled',
    initialValues: { current_password: '', password: '', password_confirmation: '' },
    validate: {
      current_password: (v) => (v ? null : 'Requerido'),
      password: (v) => (v && v.length >= 8 ? null : 'Mínimo 8 caracteres'),
      password_confirmation: (v, values) =>
        v === values.password ? null : 'Las contraseñas no coinciden',
    },
  })

  const submit = form.onSubmit(async (values) => {
    setLoading(true)
    try {
      await authService.changePassword(values)
      notifications.show({
        title: 'Contraseña actualizada',
        message: 'Tu contraseña se ha cambiado correctamente.',
        color: 'green',
      })
      onDone()
    } catch (error) {
      const errors = validationErrors(error)
      Object.entries(errors).forEach(([field, message]) => form.setFieldError(field, message))
    } finally {
      setLoading(false)
    }
  })

  return (
    <form onSubmit={submit}>
      <Stack gap="sm">
        <PasswordInput
          label="Contraseña actual"
          key={form.key('current_password')}
          {...form.getInputProps('current_password')}
        />
        <PasswordInput
          label="Nueva contraseña"
          key={form.key('password')}
          {...form.getInputProps('password')}
        />
        <PasswordInput
          label="Repetir nueva contraseña"
          key={form.key('password_confirmation')}
          {...form.getInputProps('password_confirmation')}
        />
        <Button type="submit" loading={loading} fullWidth>
          Actualizar
        </Button>
      </Stack>
    </form>
  )
}
