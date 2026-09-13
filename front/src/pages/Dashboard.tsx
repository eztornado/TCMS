import { useQuery } from '@tanstack/react-query'
import { Grid, Paper, Stack, Text, Title } from '@mantine/core'
import {
  IconCalendarEvent,
  IconPackage,
  IconShoppingCart,
  IconUsers,
} from '@tabler/icons-react'
import { api } from '@/lib/api'
import { formatMoney, formatNumber } from '@/lib/format'
import { usePermissions } from '@/hooks/usePermissions'

interface DashboardStats {
  users: number
  products: number
  orders: number
  revenue: number
  upcoming_events: number
  pending_orders: number
}

function StatCard({
  label,
  value,
  icon,
}: {
  label: string
  value: string
  icon: React.ReactNode
}) {
  return (
    <Paper p="md" radius="md" withBorder>
      <Text size="xs" c="dimmed" tt="uppercase" fw={600}>
        {label}
      </Text>
      <Text size="xl" fw={700} mt={4}>
        {value}
      </Text>
      {icon}
    </Paper>
  )
}

/** Panel de inicio: métricas del sitio según permisos del usuario. */
export default function Dashboard() {
  const { can } = usePermissions()

  const { data: stats } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => api.get<{ data: DashboardStats }>('/admin/dashboard'),
    refetchInterval: 60_000,
  })

  return (
    <Stack>
      <Title order={2}>Panel</Title>
      <Grid>
        {can('list-users') && (
          <Grid.Col span={{ base: 12, xs: 6, md: 3 }}>
            <StatCard
              label="Usuarios"
              value={formatNumber(stats?.data.users ?? 0)}
              icon={<IconUsers size={18} color="var(--mantine-color-blue-6)" />}
            />
          </Grid.Col>
        )}
        {can('list-products') && (
          <Grid.Col span={{ base: 12, xs: 6, md: 3 }}>
            <StatCard
              label="Productos"
              value={formatNumber(stats?.data.products ?? 0)}
              icon={<IconPackage size={18} color="var(--mantine-color-grape-6)" />}
            />
          </Grid.Col>
        )}
        {can('list-orders') && (
          <Grid.Col span={{ base: 12, xs: 6, md: 3 }}>
            <StatCard
              label="Pedidos"
              value={formatNumber(stats?.data.orders ?? 0)}
              icon={<IconShoppingCart size={18} color="var(--mantine-color-orange-6)" />}
            />
          </Grid.Col>
        )}
        {can('list-events') && (
          <Grid.Col span={{ base: 12, xs: 6, md: 3 }}>
            <StatCard
              label="Próximos eventos"
              value={formatNumber(stats?.data.upcoming_events ?? 0)}
              icon={<IconCalendarEvent size={18} color="var(--mantine-color-teal-6)" />}
            />
          </Grid.Col>
        )}
      </Grid>
      {can('list-orders') && (
        <Paper p="md" withBorder>
          <Text size="xs" c="dimmed" tt="uppercase" fw={600}>
            Ingresos (pedidos pagados)
          </Text>
          <Text size="2rem" fw={700} c="tornado.6">
            {formatMoney(stats?.data.revenue ?? 0)}
          </Text>
        </Paper>
      )}
    </Stack>
  )
}
