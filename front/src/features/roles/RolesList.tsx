import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  Badge,
  Button,
  Checkbox,
  Group,
  Modal,
  Paper,
  ScrollArea,
  SimpleGrid,
  Stack,
  Tabs,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { useForm } from '@mantine/form'
import { modals } from '@mantine/modals'
import { notifications } from '@mantine/notifications'
import { IconPlus, IconShieldCheck } from '@tabler/icons-react'
import { DataTable } from '@/components/crud/DataTable'
import { rolesService } from '@/services/adminService'
import { errorMessage } from '@/lib/api'
import type { Permission, Role } from '@/types/api'

/** Gestión de roles con matriz de permisos agrupada por recurso. */
export default function RolesList() {
  const [editing, setEditing] = useState<Role | 'new' | null>(null)
  const queryClient = useQueryClient()

  const { data: roles = [], isLoading, refetch } = useQuery({ queryKey: ['roles'], queryFn: rolesService.list })

  return (
    <Stack gap="md">
      <Group justify="space-between" align="center">
        <Title order={3}>Roles y permisos</Title>
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
          Nuevo rol
        </Button>
      </Group>

      <DataTable
        rows={roles}
        loading={isLoading}
        emptyMessage="Sin roles definidos"
        columns={[
          { key: 'name', label: 'Rol', render: (r) => <Text fw={500}>{r.name}</Text> },
          { key: 'label', label: 'Nombre visible', render: (r) => r.label ?? '—' },
          {
            key: 'permissions',
            label: 'Permisos',
            render: (role) => (
              <Badge variant="light" color="tornado" leftSection={<IconShieldCheck size={12} />}>
                {role.permissions.length}
              </Badge>
            ),
          },
          { key: 'users_count', label: 'Usuarios', hideOnMobile: true },
        ]}
        actions={{
          onEdit: (role) => setEditing(role),
          onDelete: async (role) => {
            await rolesService.delete(role.id)
            void refetch()
          },
        }}
      />

      {editing && (
        <RoleFormModal
          role={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            void refetch()
            // Los permisos del usuario autenticado pueden cambiar si edita su propio rol.
            void queryClient.invalidateQueries({ queryKey: ['auth'] })
          }}
        />
      )}
    </Stack>
  )
}

function RoleFormModal({
  role,
  onClose,
  onSaved,
}: {
  role: Role | null
  onClose: () => void
  onSaved: () => void
}) {
  const { data: permissions = [], isLoading: loadingPermissions } = useQuery({
    queryKey: ['permissions'],
    queryFn: rolesService.permissions,
  })

  const form = useForm({
    mode: 'uncontrolled',
    initialValues: {
      id: role?.id,
      name: role?.name ?? '',
      label: role?.label ?? '',
      permissions: role?.permissions.map((p) => p.name) ?? [],
    },
    validate: {
      name: (v) => (/^[a-zA-Z][a-zA-Z0-9 -]{1,30}$/.test(v) ? null : 'Nombre de rol no válido'),
    },
  })

  const grouped = useMemo(() => groupPermissions(permissions), [permissions])
  const selected = form.values.permissions as string[]

  const mutation = useMutation({
    mutationFn: (values: { id?: number; name: string; label: string; permissions: string[] }) =>
      rolesService.save(values),
    onSuccess: () => {
      notifications.show({ message: 'Rol guardado.', color: 'green' })
      onSaved()
    },
    onError: (error) => {
      notifications.show({ title: 'Error', message: errorMessage(error), color: 'red' })
    },
  })

  const toggleAllInGroup = (groupPermissions: Permission[], checked: boolean) => {
    const names = groupPermissions.map((p) => p.name)
    const next = checked
      ? [...new Set([...selected, ...names])]
      : selected.filter((name) => !names.includes(name))
    form.setFieldValue('permissions', next)
  }

  return (
    <Modal opened onClose={onClose} title={role ? `Rol: ${role.name}` : 'Nuevo rol'} size="70rem" centered>
      <form onSubmit={form.onSubmit((values) => mutation.mutate(values))}>
        <Stack gap="md">
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <TextInput label="Identificador" placeholder="p. ej. Editor" key={form.key('name')} {...form.getInputProps('name')} />
            <TextInput label="Nombre visible" placeholder="Editor de contenidos" key={form.key('label')} {...form.getInputProps('label')} />
          </SimpleGrid>

          <Paper withBorder p="sm">
            {loadingPermissions ? (
              <Text size="sm" c="dimmed">Cargando permisos…</Text>
            ) : (
              <Tabs defaultValue={Object.keys(grouped)[0]} keepMounted={false}>
                <Tabs.List>
                  {Object.entries(grouped).map(([group, perms]) => (
                    <Tabs.Tab key={group} value={group}>
                      {group}
                      <Badge ml={6} size="xs" variant="light">
                        {perms.filter((p) => selected.includes(p.name)).length}/{perms.length}
                      </Badge>
                    </Tabs.Tab>
                  ))}
                </Tabs.List>

                {Object.entries(grouped).map(([group, perms]) => (
                  <Tabs.Panel key={group} value={group} pt="md">
                    <Group mb="sm">
                      <Checkbox
                        label="Seleccionar todo el grupo"
                        checked={perms.every((p) => selected.includes(p.name))}
                        indeterminate={
                          perms.some((p) => selected.includes(p.name)) &&
                          !perms.every((p) => selected.includes(p.name))
                        }
                        onChange={(e) => toggleAllInGroup(perms, e.currentTarget.checked)}
                      />
                    </Group>
                    <ScrollArea h={320} scrollbarSize={6}>
                      <SimpleGrid cols={{ base: 1, md: 2, xl: 3 }} spacing="xs">
                        {perms.map((permission) => (
                          <Checkbox
                            key={permission.id}
                            value={permission.name}
                            label={permission.label ?? permission.name}
                            checked={selected.includes(permission.name)}
                            onChange={(e) => {
                              const next = e.currentTarget.checked
                                ? [...selected, permission.name]
                                : selected.filter((n) => n !== permission.name)
                              form.setFieldValue('permissions', next)
                            }}
                          />
                        ))}
                      </SimpleGrid>
                    </ScrollArea>
                  </Tabs.Panel>
                ))}
              </Tabs>
            )}
          </Paper>

          <Group justify="flex-end">
            <Button variant="subtle" color="gray" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" loading={mutation.isPending}>
              Guardar rol
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}

/** Agrupa permisos por su recurso (últimos dos segmentos, p. ej. users). */
function groupPermissions(permissions: Permission[]): Record<string, Permission[]> {
  return permissions.reduce<Record<string, Permission[]>>((groups, permission) => {
    const parts = permission.name.split('-')
    const resource = parts.length > 1 ? parts.slice(1).join('-') : permission.name
    groups[resource] = groups[resource] ?? []
    groups[resource].push(permission)
    return groups
  }, {})
}

/** Confirmación de borrado de rol con aviso si tiene usuarios. */
export function confirmRoleDelete(role: Role, onConfirm: () => void): void {
  modals.openConfirmModal({
    title: `Eliminar rol ${role.name}`,
    children: (
      <Text size="sm">
        {role.users_count
          ? `Este rol tiene ${role.users_count} usuarios asignados. Los usuarios perderán esos permisos.`
          : 'Esta acción no se puede deshacer.'}
      </Text>
    ),
    labels: { confirm: 'Eliminar', cancel: 'Cancelar' },
    confirmProps: { color: 'red' },
    onConfirm,
  })
}
