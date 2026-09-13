import { Center, Loader } from '@mantine/core'

/** Fallback del lazy-loading de páginas. */
export function PageFallback() {
  return (
    <Center mih="40vh">
      <Loader size="sm" />
    </Center>
  )
}
