import { useEffect } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import {
  AppShell,
  Burger,
  Code,
  Group,
  Menu,
  Modal,
  NavLink as MantineNavLink,
  ScrollArea,
  Stack,
  Text,
  UnstyledButton,
  useMantineColorScheme,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconChevronRight,
  IconLogout,
  IconMoon,
  IconSettings,
  IconSun,
  IconUserCog,
} from '@tabler/icons-react'
import { useAuthStore } from '@/store/authStore'
import { useLogout } from '@/hooks/useAuth'
import { usePermissions } from '@/hooks/usePermissions'
import { config } from '@/config'
import type { MenuItem } from '@/types/api'
import ChangePasswordModal from '@/components/layout/ChangePasswordModal'
import SyncIndicator from '@/features/sync/SyncIndicator'

function NavItem({ item, onNavigate }: { item: MenuItem; onNavigate: () => void }) {
  if (item.children?.length) {
    return (
      <Menu shadow="md" trigger="hover" openDelay={100} closeDelay={150} withinPortal>
        <Menu.Target>
          <MantineNavLink
            label={item.label}
            leftSection={<IconChevronRight size={0} />}
            rightSection={<IconChevronRight size={14} style={{ transform: 'rotate(90deg)' }} />}
            childrenOffset={0}
          />
        </Menu.Target>
        <Menu.Dropdown>
          {item.children.map((child) => (
            <Menu.Item
              key={child.to}
              component={NavLink}
              to={child.to}
              onClick={onNavigate}
            >
              {child.label}
            </Menu.Item>
          ))}
        </Menu.Dropdown>
      </Menu>
    )
  }
  return (
    <MantineNavLink component={NavLink} to={item.to} label={item.label} onClick={onNavigate} />
  )
}

/**
 * Layout principal del panel. El menú lo genera el backend en función de los
 * permisos del usuario (`GET /auth/menu`): el front no decide qué puede ver.
 */
export default function AppLayout() {
  const [mobileOpen, { toggle: toggleMobile, close: closeMobile }] = useDisclosure(false)
  const [passwordOpen, { toggle: togglePassword }] = useDisclosure(false)
  const { user, menu } = useAuthStore()
  const { can } = usePermissions()
  const { setColorScheme, colorScheme } = useMantineColorScheme()
  const logout = useLogout()
  const location = useLocation()
  const navigate = useNavigate()

  useEffect(() => {
    closeMobile()
  }, [location.pathname, location.search, closeMobile])

  const filterMenu = (items: MenuItem[]): MenuItem[] =>
    items
      .filter((item) => !item.permission || can(item.permission))
      .map((item) => (item.children ? { ...item, children: filterMenu(item.children) } : item))
      .filter((item) => !item.children || item.children.length > 0)

  const visibleMenu = filterMenu(menu)

  return (
    <AppShell
      header={{ height: 56 }}
      navbar={{ width: 250, breakpoint: 'sm', collapsed: { mobile: !mobileOpen } }}
      padding="md"
    >
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <Burger opened={mobileOpen} onClick={toggleMobile} hiddenFrom="sm" size="sm" />
            <Group gap={8} wrap="nowrap" onClick={() => navigate('/')} style={{ cursor: 'pointer' }}>
              <Text fw={700} size="lg" c="tornado.6" lh={1}>
                {config.appShortName}
              </Text>
              <Text size="xs" c="dimmed" visibleFrom="sm" lh={1.2}>
                {config.appName}
              </Text>
            </Group>
          </Group>

          <Group gap="xs" wrap="nowrap">
            <SyncIndicator />
            <MantineNavLink
              variant="subtle"
              label={colorScheme === 'dark' ? 'Claro' : 'Oscuro'}
              leftSection={colorScheme === 'dark' ? <IconSun size={16} /> : <IconMoon size={16} />}
              onClick={() => setColorScheme(colorScheme === 'dark' ? 'light' : 'dark')}
              w={110}
            />
            <Menu shadow="md" width={220} position="bottom-end" withinPortal>
              <Menu.Target>
                <UnstyledButton px="xs" py={6} style={{ borderRadius: 8 }}>
                  <Group gap="xs" wrap="nowrap">
                    <IconUserCog size={18} />
                    <Stack gap={0} visibleFrom="sm">
                      <Text size="sm" fw={500} lh={1.1}>
                        {user?.name}
                      </Text>
                      <Text size="xs" c="dimmed" lh={1.1}>
                        {user?.roles.map((r) => r.label ?? r.name).join(', ')}
                      </Text>
                    </Stack>
                  </Group>
                </UnstyledButton>
              </Menu.Target>
              <Menu.Dropdown>
                <Menu.Label>{user?.email}</Menu.Label>
                <Menu.Item leftSection={<IconSettings size={16} />} onClick={togglePassword}>
                  Cambiar contraseña
                </Menu.Item>
                <Menu.Divider />
                <Menu.Item
                  color="red"
                  leftSection={<IconLogout size={16} />}
                  onClick={() => logout.mutate()}
                >
                  Cerrar sesión
                </Menu.Item>
              </Menu.Dropdown>
            </Menu>
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar py="sm">
        <AppShell.Section grow component={ScrollArea} scrollbarSize={6}>
          <Stack gap={2} px="sm">
            {visibleMenu.map((item) => (
              <NavItem key={item.to} item={item} onNavigate={closeMobile} />
            ))}
          </Stack>
        </AppShell.Section>
        <AppShell.Section px="sm" pb="xs">
          <Code fw={600} c="dimmed" style={{ fontSize: 10 }}>
            TCMS v1.0
          </Code>
        </AppShell.Section>
      </AppShell.Navbar>

      <AppShell.Main>
        <Outlet />
      </AppShell.Main>

      <Modal opened={passwordOpen} onClose={togglePassword} title="Cambiar contraseña" centered>
        <ChangePasswordModal onDone={togglePassword} />
      </Modal>
    </AppShell>
  )
}
