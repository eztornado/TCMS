import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  ActionIcon,
  Button,
  Card,
  Center,
  CopyButton,
  FileButton,
  Group,
  Image,
  Loader,
  Modal,
  Paper,
  Progress,
  Stack,
  Text,
  TextInput,
  Title,
  Tooltip,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { modals } from '@mantine/modals'
import { IconCopy, IconFileUpload, IconTrash, IconUpload } from '@tabler/icons-react'
import { mediaService } from '@/services/adminService'
import { api, unwrap } from '@/lib/api'
import { formatBytes, formatDateTime } from '@/lib/format'
import { errorMessage } from '@/lib/api'
import { config } from '@/config'

interface MediaRow {
  id: number
  name: string
  file_name: string
  mime_type: string
  size: number
  url: string
  thumbnail_url?: string
  alt: string | null
  created_at: string
}

function fetchMedia(): Promise<MediaRow[]> {
  return api.get<{ data: MediaRow[] }>('/admin/media').then((r) => unwrap(r))
}

/** Biblioteca de medios: subida múltiple, rejilla, metadatos y borrado. */
export default function MediaLibrary() {
  const queryClient = useQueryClient()
  const [uploadProgress, setUploadProgress] = useState<number | null>(null)
  const [detail, setDetail] = useState<MediaRow | null>(null)
  const [altDraft, setAltDraft] = useState('')
  const [pendingFiles, setPendingFiles] = useState<File[]>([])

  const { data = [], isLoading } = useQuery({ queryKey: ['media'], queryFn: fetchMedia })

  const upload = useMutation({
    mutationFn: async (files: File[]) => {
      for (const [index, file] of files.entries()) {
        await mediaService.upload(file, (pct) =>
          setUploadProgress(Math.round(((index + pct / 100) / files.length) * 100)),
        )
      }
      return files
    },
    onSuccess: (files) => {
      notifications.show({ message: `${files.length} fichero(s) subido(s).`, color: 'green' })
      setUploadProgress(null)
      setPendingFiles([])
      void queryClient.invalidateQueries({ queryKey: ['media'] })
    },
    onError: (error) => {
      setUploadProgress(null)
      notifications.show({ title: 'Error al subir', message: errorMessage(error), color: 'red' })
    },
  })

  const remove = (media: MediaRow) => {
    modals.openConfirmModal({
      title: 'Eliminar fichero',
      children: <Text size="sm">Se borrará también de donde esté en uso. ¿Continuar?</Text>,
      labels: { confirm: 'Eliminar', cancel: 'Cancelar' },
      confirmProps: { color: 'red' },
      onConfirm: async () => {
        await mediaService.delete(media.id)
        void queryClient.invalidateQueries({ queryKey: ['media'] })
      },
    })
  }

  const saveAlt = async () => {
    if (!detail) return
    await mediaService.update(detail.id, { alt: altDraft })
    notifications.show({ message: 'Texto alternativo actualizado.', color: 'green' })
    setDetail(null)
    void queryClient.invalidateQueries({ queryKey: ['media'] })
  }

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Title order={3}>Biblioteca de medios</Title>
        <Group>
          {pendingFiles.length > 0 && (
            <Group gap="xs">
              <Text size="sm" c="dimmed">
                {pendingFiles.length} seleccionado(s)
              </Text>
              <Button
                leftSection={<IconUpload size={16} />}
                loading={upload.isPending}
                onClick={() => upload.mutate(pendingFiles)}
              >
                Subir
              </Button>
            </Group>
          )}
          <FileButton
            onChange={(files) => files && setPendingFiles(Array.from(files))}
            multiple
            accept={config.allowedImageTypes.join(',')}
          >
            {(props) => (
              <Button variant="light" leftSection={<IconFileUpload size={16} />} {...props}>
                Elegir ficheros
              </Button>
            )}
          </FileButton>
        </Group>
      </Group>

      {uploadProgress !== null && <Progress value={uploadProgress} animated />}

      <Paper p="xl" withBorder style={{ borderStyle: 'dashed' }}>
        <Center>
          <Stack align="center" gap={4}>
            <FileButton
              onChange={(files) =>
                files && upload.mutate(Array.isArray(files) ? Array.from(files) : [files])
              }
              multiple
              accept={config.allowedImageTypes.join(',')}
            >
              {(props) => (
                <ActionIcon {...props} variant="light" size={48} radius="xl" aria-label="Subir ficheros">
                  <IconUpload size={22} />
                </ActionIcon>
              )}
            </FileButton>
            <Text size="sm" c="dimmed">
              Pulsa para subir imágenes (máx. {config.maxUploadSizeMb} MB por fichero)
            </Text>
          </Stack>
        </Center>
      </Paper>

      {isLoading ? (
        <Center py="xl">
          <Loader size="sm" />
        </Center>
      ) : data.length === 0 ? (
        <Text c="dimmed" ta="center" py="xl">
          Aún no hay ficheros en la biblioteca.
        </Text>
      ) : (
        <Group gap="sm" align="stretch">
          {data.map((media) => (
            <Card key={media.id} p={0} w={180} withBorder>
              <Card.Section>
                <Image
                  src={media.thumbnail_url ?? media.url}
                  alt={media.alt ?? media.name}
                  h={120}
                  fit="cover"
                  onClick={() => {
                    setDetail(media)
                    setAltDraft(media.alt ?? '')
                  }}
                  style={{ cursor: 'zoom-in' }}
                />
              </Card.Section>
              <Stack gap={4} p="xs">
                <Text size="xs" lineClamp={1} fw={500}>
                  {media.name}
                </Text>
                <Group justify="space-between" gap={4}>
                  <Text size="xs" c="dimmed">
                    {formatBytes(media.size)}
                  </Text>
                  <Group gap={0}>
                    <CopyButton value={media.url}>
                      {({ copied, copy }) => (
                        <Tooltip label={copied ? 'Copiado' : 'Copiar URL'}>
                          <ActionIcon size="sm" onClick={copy} aria-label="Copiar URL">
                            <IconCopy size={14} color={copied ? 'var(--mantine-color-green-6)' : undefined} />
                          </ActionIcon>
                        </Tooltip>
                      )}
                    </CopyButton>
                    <Tooltip label="Eliminar">
                      <ActionIcon size="sm" color="red" onClick={() => remove(media)} aria-label="Eliminar">
                        <IconTrash size={14} />
                      </ActionIcon>
                    </Tooltip>
                  </Group>
                </Group>
              </Stack>
            </Card>
          ))}
        </Group>
      )}

      <Modal opened={Boolean(detail)} onClose={() => setDetail(null)} title={detail?.name} size="lg">
        {detail && (
          <Stack gap="sm">
            <Image src={detail.url} alt={detail.alt ?? ''} radius="md" maw={480} mx="auto" />
            <Text size="sm" c="dimmed">
              {detail.file_name} · {formatBytes(detail.size)} · {formatDateTime(detail.created_at)}
            </Text>
            <TextInput
              label="Texto alternativo (accesibilidad y SEO)"
              value={altDraft}
              onChange={(e) => setAltDraft(e.currentTarget.value)}
            />
            <Group justify="flex-end">
              <CopyButton value={detail.url}>
                {({ copy }) => (
                  <Button variant="light" leftSection={<IconCopy size={16} />} onClick={copy}>
                    Copiar URL
                  </Button>
                )}
              </CopyButton>
              <Button onClick={saveAlt}>Guardar</Button>
            </Group>
          </Stack>
        )}
      </Modal>
    </Stack>
  )
}
