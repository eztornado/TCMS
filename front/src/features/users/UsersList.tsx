import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Badge,
  Button,
  Group,
  Modal,
  MultiSelect,
  PasswordInput,
  Stack,
  Switch,
  TextInput,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { IconPlus, IconShield } from '@tabler/icons-react'
import { notifications } from '@mantine/notifications'
import { DataTable, type DataColumn } from '@/components/crud/DataTable'
import { useListQuery } from '@/hooks/useListQuery'
import { usersService, type UserPayload } from '@/services/adminService'
import { rolesService } from '@/services/adminService'
import { validationErrors } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import type { Role, User } from '@/types/api'

const ROLE_COLORS: Record<string, string> = {
  'Super Admin': 'grape',
  Admin: 'red',
  Editor: 'blue',
  'Gestor de tienda': 'teal',
  'Gestor de eventos': 'orange',
}

export default function UsersList() {
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<User | 'new' | null>(null)

  const list = useListQuery<User>({ resource: 'users', endpoint: '/admin/users', search })
  const { data: roles } = useQuery({
    queryKey: ['roles', 'all'],
    queryFn: rolesService.list,
    staleTime: 60_000,
  })

  const columns: DataColumn<User>[] = [
    { key: 'id', label: '#', sortable: true },
    { key: 'name', label: 'Nombre', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    {
      key: 'roles',
      label: 'Roles',
      render: (user) => (
        <Group gap={4}>
          {user.roles.map((role: Pick<Role, 'id' | 'name'>) => (
            <Badge key={role.id} size="sm" variant="light" color={ROLE_COLORS[role.name] ?? 'gray'}>
              {role.name}
            </Badge>
          ))}
        </Group>
      ),
    },
    {
      key: 'is_active',
      label: 'Activo',
      render: (user) => (
        <Badge size="sm" color={user.is_active ? 'green' : 'gray'} variant="light">
          {user.is_active ? 'Sí' : 'No'}
        </Badge>
      ),
      hideOnMobile: true,
    },
    { key: 'last_login_at', label: 'Último acceso', render: (u) => formatDateTime(u.last_login_at), hideOnMobile: true },
    { key: 'created_at', label: 'Creado', render: (u) => formatDateTime(u.created_at), hideOnMobile: true, sortable: true },
  ]

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="nowrap">
        <TextInput
          placeholder="Buscar por nombre o email…"
          value={search}
          onChange={(e) => setSearch(e.currentTarget.value)}
          w={320}
          maxLength={100}
        />
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
          Nuevo usuario
        </Button>
      </Group>

      <DataTable
        rows={list.data}
        loading={list.isLoading}
        emptyMessage="No hay usuarios"
        columns={columns}
        pagination={{
          page: Number(list.raw?.current_page ?? 1),
          lastPage: list.lastPage,
          total: list.total,
          perPage: Number(list.raw?.per_page ?? 25),
          onPageChange: (page) => list.setPage(page),
          onPerPageChange: (per_page) => list.setPerPage(per_page),
        }}
        sorting={{ by: list.sort, dir: list.dir, onSort: list.toggleSort }}
        actions={{
          onEdit: (user) => setEditing(user),
          onDelete: async (user) => {
            await usersService.delete(user.id)
            list.reload()
          },
        }}
      />

      {editing && (
        <UserFormModal
          user={editing === 'new' ? null : editing}
          roles={roles ?? []}
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

function UserFormModal({
  user,
  roles,
  onClose,
  onSaved,
}: {
  user: User | null
  roles: Role[]
  onClose: () => void
  onSaved: () => void
}) {
  const queryClient = useQueryClient()
  const form = useForm<UserPayload>({
    mode: 'uncontrolled',
    initialValues: {
      id: user?.id,
      name: user?.name ?? '',
      email: user?.email ?? '',
      password: '',
      is_active: user?.is_active ?? true,
      roles: user?.roles.map((r) => r.name) ?? [],
    },
    validate: {
      name: (v) => (v.trim().length >= 2 ? null : 'Nombre demasiado corto'),
      email: (v) => (/^\S+@\S+$/.test(v) ? null : 'Email no válido'),
      password: (v) => (!user || (v ?? '').length >= 8 ? null : 'Mínimo 8 caracteres'),
    },
  })

  const mutation = useMutation({
    mutationFn: (payload: UserPayload) => usersService.save(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['users'] })
      notifications.show({ message: 'Usuario guardado.', color: 'green' })
      onSaved()
    },
    onError: (error) => {
      const errors = validationErrors(error)
      Object.entries(errors).forEach(([field, message]) => form.setFieldError(field, message))
    },
  })

  return (
    <Modal
      opened
      onClose={onClose}
      title={user ? `Editar: ${user.name}` : 'Nuevo usuario'}
      centered
      size="md"
    >
      <form onSubmit={form.onSubmit((values) => mutation.mutate(values))}>
        <Stack gap="sm">
          <TextInput label="Nombre" key={form.key('name')} {...form.getInputProps('name')} />
          <TextInput
            label="Email"
            key={form.key('email')}
            {...form.getInputProps('email')}
            disabled={Boolean(user)}
          />
          <PasswordInput
            label={user ? 'Nueva contraseña (opcional)' : 'Contraseña'}
            key={form.key('password')}
            {...form.getInputProps('password')}
          />
          <MultiSelect
            label="Roles"
            placeholder="Sin roles"
            data={roles.map((role) => ({ value: role.name, label: role.label ?? role.name }))}
            leftSection={<IconShield size={14} />}
            key={form.key('roles')}
            {...form.getInputProps('roles')}
          />
          <Switch
            label="Cuenta activa"
            key={form.key('is_active')}
            {...form.getInputProps('is_active', { type: 'checkbox' })}
          />
          <Group justify="flex-end" mt="sm">
            <Button variant="subtle" color="gray" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={mutation.isPending}>
              Guardar
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
