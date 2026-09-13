# custom/ — código específico de cada proyecto

El CORE del panel vive en `src/` (features, components, hooks, services).
Todo lo específico de un proyecto va aquí, de forma que **actualizar el core
con `git pull` nunca entra en conflicto** con tu código.

## Añadir páginas al panel

Crea `custom/routes/<nombre>.tsx` exportando una lista de rutas:

```tsx
import { lazy } from 'react'
import type { CustomRoute } from '@/router'

const MiPagina = lazy(() => import('./pages/MiPagina'))

export default [
  { path: '/mi-proyecto/paginas', element: <MiPagina />, permission: 'list-cm-mi-entidad' },
] satisfies CustomRoute[]
```

Las rutas se inyectan automáticamente dentro del layout del panel, con su
guard de permiso. El menú correspondiente se define en el BACKEND
(`menus` + `menu_items`, ver `docs/CUSTOM-MODELS.md`), como todo el panel.

## Estructura recomendada

```
custom/
├── routes/         # manifiestos de rutas del proyecto (uno o varios ficheros)
├── pages/          # páginas del proyecto
├── components/     # componentes propios
├── services/       # servicios de API propios (usa @/lib/api)
└── README.md
```

## Reglas para mantener el core actualizable

- No edites ficheros fuera de `custom/` (y `api/Modules/` en el backend).
- Reutiliza el kit del core: `DataTable`, `useListQuery`, `usePermissions`,
  `@/lib/api`, `@/lib/format`. Si te falta algo genérico, propón subirlo al
  core (así lo heredan el resto de proyectos).
- El menú del panel SIEMPRE viene filtrado por permisos desde el backend
  (`GET /api/auth/menu`): no hardcodees navegación.
