import { useEffect, useMemo, useState, type ReactNode } from 'react'
import {
  Box,
  Center,
  Checkbox,
  Group,
  LoadingOverlay,
  Pagination,
  Select,
  Table,
  Text,
  Tooltip,
  Button,
} from '@mantine/core'
import {
  IconArrowsSort,
  IconChevronDown,
  IconChevronUp,
  IconDatabaseOff,
  IconEye,
  IconPencil,
  IconReload,
  IconTrash,
} from '@tabler/icons-react'
import { modals } from '@mantine/modals'
import { useMediaQuery } from '@mantine/hooks'
import { config } from '@/config'

export interface DataColumn<T> {
  /** Clave del objeto, admite rutas anidadas `cliente.nombre`. */
  key: string
  label: ReactNode
  render?: (item: T) => ReactNode
  sortable?: boolean
  hideOnMobile?: boolean
  width?: number | string
  minWidth?: number | string
  textAlign?: 'left' | 'center' | 'right'
  /** Valor usado para exportar/seleccionar (por defecto el valor plano de la clave). */
  value?: (item: T) => unknown
}

export interface RowActions<T> {
  onView?: (item: T) => void
  onEdit?: (item: T) => void
  onDelete?: (item: T) => void | Promise<void>
  /** Acciones extra; `visible` permite ocultarlas por fila. */
  custom?: Array<{
    label: string
    icon?: ReactNode
    onClick: (item: T) => void
    visible?: (item: T) => boolean
  }>
}

export interface BulkAction<T> {
  label: string
  icon?: ReactNode
  color?: string
  onRun: (items: T[]) => void | Promise<void>
  confirm?: string
}

interface DataTableProps<T extends { id: number | string }> {
  columns: DataColumn<T>[]
  rows: T[]
  loading?: boolean
  emptyMessage?: string
  /** Paginación server-side; omítela para tablas de cliente sin paginar. */
  pagination?: {
    page: number
    lastPage: number
    total: number
    perPage: number
    onPageChange: (page: number) => void
    onPerPageChange?: (perPage: number) => void
  }
  sorting?: { by: string | null; dir: 'asc' | 'desc' | null; onSort: (key: string) => void }
  actions?: RowActions<T>
  bulkActions?: BulkAction<T>[]
  selectable?: boolean
  isRowDisabled?: (item: T) => boolean
  onRowClick?: (item: T) => void
  toolbar?: ReactNode
  dense?: boolean
}

/** Heurística de anchos por nombre de columna (ahorra definir anchos a mano). */
function inferWidth(key: string): string | number {
  const k = key.toLowerCase()
  if (k === 'id') return 70
  if (k.includes('email')) return 220
  if (k.includes('fecha') || k.includes('date') || k.includes('_at')) return 150
  if (k.includes('precio') || k.includes('price') || k.includes('total') || k.includes('importe'))
    return 110
  if (k.includes('image') || k.includes('imagen') || k.includes('foto')) return 90
  if (k.includes('status') || k.includes('estado')) return 140
  if (k.includes('actions')) return 120
  return 'auto'
}

function plainValue<T extends { id: number | string }>(item: T, key: string): ReactNode {
  const value = key.includes('.')
    ? key.split('.').reduce<unknown>((obj, k) => (obj as Record<string, unknown>)?.[k], item)
    : (item as unknown as Record<string, unknown>)[key]

  if (value === null || value === undefined || value === '') {
    return <Text c="dimmed" size="sm">—</Text>
  }
  if (typeof value === 'boolean') {
    return (
      <Text size="sm" c={value ? 'green' : 'red'}>
        {value ? 'Sí' : 'No'}
      </Text>
    )
  }
  return String(value)
}

/**
 * Tabla de datos genérica de TCMS. No hace fetching: recibe `rows` y devuelve
 * callbacks, de modo que funciona igual con cualquier fuente de datos.
 */
export function DataTable<T extends { id: number | string }>({
  columns,
  rows,
  loading = false,
  emptyMessage = 'No hay registros',
  pagination,
  sorting,
  actions,
  bulkActions = [],
  selectable = false,
  isRowDisabled,
  onRowClick,
  toolbar,
  dense = false,
}: DataTableProps<T>) {
  const isMobile = useMediaQuery('(max-width: 62em)')
  const [selected, setSelected] = useState<Set<string | number>>(new Set())

  const visibleColumns = useMemo(
    () => (isMobile ? columns.filter((c) => !c.hideOnMobile) : columns),
    [columns, isMobile],
  )

  const selectableRows = rows.filter((r) => !isRowDisabled?.(r))
  const allSelected =
    selectableRows.length > 0 && selectableRows.every((r) => selected.has(r.id))
  const someSelected = selectableRows.some((r) => selected.has(r.id)) && !allSelected
  const selectedRows = rows.filter((r) => selected.has(r.id))

  useEffect(() => {
    // Limpia la selección que ya no existe tras recargar datos.
    if (selected.size === 0) return
    const ids = new Set(rows.map((r) => r.id))
    const next = new Set([...selected].filter((id) => ids.has(id)))
    if (next.size !== selected.size) setSelected(next)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows])

  const toggleAll = () => {
    if (allSelected) setSelected(new Set())
    else setSelected(new Set(selectableRows.map((r) => r.id)))
  }

  const runBulk = (action: BulkAction<T>) => {
    const run = () => {
      void Promise.resolve(action.onRun(selectedRows)).finally(() => {
        setSelected(new Set())
      })
    }
    if (action.confirm) {
      modals.openConfirmModal({
        title: action.label,
        children: <Text size="sm">{action.confirm}</Text>,
        labels: { confirm: 'Confirmar', cancel: 'Cancelar' },
        confirmProps: { color: action.color ?? 'red' },
        onConfirm: run,
      })
    } else {
      run()
    }
  }

  const deleteRow = (item: T) => {
    modals.openConfirmModal({
      title: 'Eliminar registro',
      children: (
        <Text size="sm">
          Esta acción no se puede deshacer. ¿Seguro que quieres eliminarlo?
        </Text>
      ),
      labels: { confirm: 'Eliminar', cancel: 'Cancelar' },
      confirmProps: { color: 'red' },
      onConfirm: async () => {
        await actions?.onDelete?.(item)
        setSelected((prev) => {
          const next = new Set(prev)
          next.delete(item.id)
          return next
        })
      },
    })
  }

  const hasActions = actions && (actions.onView || actions.onEdit || actions.onDelete || actions.custom?.length)

  if (!loading && rows.length === 0) {
    return (
      <Box pos="relative">
        {toolbar}
        <Center py="xl" style={{ flexDirection: 'column', gap: 8 }}>
          <IconDatabaseOff size={36} stroke={1.2} color="var(--mantine-color-dimmed)" />
          <Text c="dimmed">{emptyMessage}</Text>
          <Button variant="subtle" size="xs" leftSection={<IconReload size={14} />} onClick={() => window.location.reload()}>
            Reintentar
          </Button>
        </Center>
      </Box>
    )
  }

  return (
    <Box pos="relative">
      <LoadingOverlay visible={loading} overlayProps={{ blur: 1 }} zIndex={200} />

      {(toolbar || (selectable && selected.size > 0)) && (
        <Group justify="space-between" mb="sm" gap="xs" wrap="nowrap">
          <Box style={{ flex: 1, minWidth: 0 }}>{toolbar}</Box>
          {selectable && selected.size > 0 && (
            <Group gap="xs" wrap="nowrap" style={{ flexShrink: 0 }}>
              <Text size="sm" c="dimmed">
                {selected.size} sel.
              </Text>
              {bulkActions.map((action) => (
                <Button
                  key={action.label}
                  size="xs"
                  variant="light"
                  color={action.color ?? 'blue'}
                  leftSection={action.icon ?? <IconTrash size={14} />}
                  onClick={() => runBulk(action)}
                >
                  {action.label}
                </Button>
              ))}
            </Group>
          )}
        </Group>
      )}

      <Table.ScrollContainer minWidth={700} type="native">
        <Table
          striped={rows.length > 3}
          highlightOnHover={Boolean(onRowClick)}
          verticalSpacing={dense ? 'xs' : 'sm'}
          horizontalSpacing="md"
          withTableBorder
          withColumnBorders={false}
        >
          <Table.Thead style={{ position: 'sticky', top: 0, zIndex: 5, background: 'var(--mantine-color-body)' }}>
            <Table.Tr>
              {selectable && (
                <Table.Th w={44}>
                  <Checkbox
                    aria-label="Seleccionar todo"
                    checked={allSelected}
                    indeterminate={someSelected}
                    onChange={toggleAll}
                  />
                </Table.Th>
              )}
              {visibleColumns.map((column) => {
                const isSorted = sorting?.by === column.key
                return (
                  <Table.Th
                    key={column.key}
                    w={column.width ?? inferWidth(column.key)}
                    maw={column.minWidth ? undefined : undefined}
                    style={{ minWidth: typeof column.minWidth === 'number' ? column.minWidth : undefined }}
                    ta={column.textAlign}
                  >
                    {column.sortable && sorting ? (
                      <Box
                        component="button"
                        type="button"
                        onClick={() => sorting.onSort(column.key)}
                        style={{
                          background: 'none',
                          border: 'none',
                          cursor: 'pointer',
                          padding: 0,
                          display: 'flex',
                          alignItems: 'center',
                          gap: 4,
                        }}
                      >
                        <Text size="sm" fw={500}>
                          {column.label}
                        </Text>
                        {isSorted ? (
                          sorting.dir === 'asc' ? (
                            <IconChevronUp size={14} />
                          ) : (
                            <IconChevronDown size={14} />
                          )
                        ) : (
                          <IconArrowsSort size={12} color="var(--mantine-color-dimmed)" />
                        )}
                      </Box>
                    ) : (
                      <Text size="sm" fw={500}>
                        {column.label}
                      </Text>
                    )}
                  </Table.Th>
                )
              })}
              {hasActions && <Table.Th w={inferWidth('actions')} ta="right">Acciones</Table.Th>}
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {rows.map((row) => {
              const disabled = isRowDisabled?.(row) ?? false
              return (
                <Table.Tr
                  key={row.id}
                  onClick={onRowClick && !disabled ? () => onRowClick(row) : undefined}
                  style={{ cursor: onRowClick && !disabled ? 'pointer' : undefined }}
                  data-disabled={disabled || undefined}
                  bg={disabled ? 'var(--mantine-color-gray-0)' : undefined}
                >
                  {selectable && (
                    <Table.Td onClick={(e) => e.stopPropagation()}>
                      <Checkbox
                        aria-label={`Seleccionar ${row.id}`}
                        checked={selected.has(row.id)}
                        disabled={disabled}
                        onChange={() =>
                          setSelected((prev) => {
                            const next = new Set(prev)
                            if (next.has(row.id)) next.delete(row.id)
                            else next.add(row.id)
                            return next
                          })
                        }
                      />
                    </Table.Td>
                  )}
                  {visibleColumns.map((column) => (
                    <Table.Td key={column.key} ta={column.textAlign}>
                      {column.render ? column.render(row) : plainValue(row, column.key)}
                    </Table.Td>
                  ))}
                  {hasActions && (
                    <Table.Td ta="right" onClick={(e) => e.stopPropagation()}>
                      <Group gap={4} justify="flex-end" wrap="nowrap">
                        {actions?.custom
                          ?.filter((a) => a.visible?.(row) ?? true)
                          .map((a) => (
                            <Tooltip key={a.label} label={a.label}>
                              <button
                                type="button"
                                className="tcms-row-action"
                                onClick={() => a.onClick(row)}
                              >
                                {a.icon}
                              </button>
                            </Tooltip>
                          ))}
                        {actions?.onView && (
                          <Tooltip label="Ver">
                            <button
                              type="button"
                              className="tcms-row-action"
                              onClick={() => actions.onView?.(row)}
                            >
                              <IconEye size={16} />
                            </button>
                          </Tooltip>
                        )}
                        {actions?.onEdit && (
                          <Tooltip label="Editar">
                            <button
                              type="button"
                              className="tcms-row-action"
                              onClick={() => actions.onEdit?.(row)}
                            >
                              <IconPencil size={16} />
                            </button>
                          </Tooltip>
                        )}
                        {actions?.onDelete && (
                          <Tooltip label="Eliminar">
                            <button
                              type="button"
                              className="tcms-row-action"
                              data-danger
                              onClick={() => deleteRow(row)}
                            >
                              <IconTrash size={16} />
                            </button>
                          </Tooltip>
                        )}
                      </Group>
                    </Table.Td>
                  )}
                </Table.Tr>
              )
            })}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>

      {pagination && pagination.lastPage > 1 && (
        <Group justify="space-between" mt="sm" gap="xs" wrap="nowrap">
          <Text size="sm" c="dimmed">
            Mostrando {rows.length} de {pagination.total.toLocaleString(config.locale)} registros
          </Text>
          <Group gap="xs" wrap="nowrap">
            {pagination.onPerPageChange && (
              <Select
                size="xs"
                w={90}
                data={config.pageSizeOptions}
                value={String(pagination.perPage)}
                onChange={(value) => value && pagination.onPerPageChange?.(Number(value))}
                allowDeselect={false}
                aria-label="Registros por página"
              />
            )}
            <Pagination
              size="sm"
              total={pagination.lastPage}
              value={pagination.page}
              onChange={pagination.onPageChange}
              siblings={1}
              boundaries={1}
            />
          </Group>
        </Group>
      )}
    </Box>
  )
}
