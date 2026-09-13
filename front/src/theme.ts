import { createTheme, type MantineColorsTuple } from '@mantine/core'

/** Turquesa "tornado" como color de marca. */
const tornado: MantineColorsTuple = [
  '#e3f7f6',
  '#cfeeea',
  '#a5dedb',
  '#78cdc9',
  '#54bfb9',
  '#40b6b0',
  '#34b3ac',
  '#289c96',
  '#1f8a85',
  '#117771',
]

export const theme = createTheme({
  primaryColor: 'tornado',
  primaryShade: 6,
  colors: { tornado },
  defaultRadius: 'md',
  fontFamily:
    'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
  headings: { fontWeight: '600' },
  // En Mantine 8 los defaultProps globales viven en `components` por componente.
  components: {
    Button: { defaultProps: { fw: 500 } },
    Paper: { defaultProps: { shadow: 'xs' } },
    Card: { defaultProps: { withBorder: true }, styles: { root: { overflow: 'visible' } } },
    TextInput: { defaultProps: { size: 'sm' } },
    Select: { defaultProps: { size: 'sm' } },
    ActionIcon: { defaultProps: { variant: 'subtle', color: 'dark' } },
  },
})
