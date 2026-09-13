import { Suspense, lazy, type ReactNode } from 'react'
import { createBrowserRouter, Route, Routes, RouterProvider } from 'react-router-dom'
import AppLayout from '@/components/layout/AppLayout'
import { RequireAuth, RequirePermission } from '@/components/guards/RequirePermission'
import Dashboard from '@/pages/Dashboard'
import Login from '@/pages/auth/Login'
import { ForbiddenPage, NotFoundPage } from '@/pages/errors/ErrorPages'
import { PageFallback } from '@/components/layout/PageFallback'

/**
 * Punto de extensión de proyecto: cada fichero en `custom/routes/*.tsx`
 * exporta una lista de rutas que se inyectan DENTRO del layout del panel.
 * Así un proyecto añade pantallas sin tocar este router (y las
 * actualizaciones del core nunca entran en conflicto con su código).
 *
 * Ver front/src/custom/README.md
 */
const customRouteModules = import.meta.glob<{ default: CustomRoute[] }>('./custom/routes/*.tsx', {
  eager: true,
})

export interface CustomRoute {
  path: string
  element: ReactNode
  /** Permiso requerido (opcional). */
  permission?: string
}

const UsersList = lazy(() => import('@/features/users/UsersList'))
const RolesList = lazy(() => import('@/features/roles/RolesList'))
const AuditList = lazy(() => import('@/features/audit/AuditList'))
const MediaLibrary = lazy(() => import('@/features/media/MediaLibrary'))
const SettingsPage = lazy(() => import('@/features/settings/SettingsPage'))
const CustomModelsList = lazy(() => import('@/features/content/CustomModelsList'))
const CustomModelBuilder = lazy(() => import('@/features/content/CustomModelBuilder'))
const CustomModelEntries = lazy(() => import('@/features/content/CustomModelEntries'))
const EventsList = lazy(() => import('@/features/events/EventsList'))
const BookingsList = lazy(() => import('@/features/events/BookingsList'))
const ProductsList = lazy(() => import('@/features/shop/ProductsList'))
const OrdersList = lazy(() => import('@/features/shop/OrdersList'))
const CouponsList = lazy(() => import('@/features/shop/CouponsList'))

/** Árbol de rutas. Los guards por permiso envuelven cada grupo de páginas. */
function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<Dashboard />} />

          {/* Usuarios y roles */}
          <Route
            path="/users"
            element={
              <RequirePermission permission="list-users">
                <UsersList />
              </RequirePermission>
            }
          />
          <Route
            path="/roles"
            element={
              <RequirePermission permission="list-roles">
                <RolesList />
              </RequirePermission>
            }
          />

          {/* Auditoría */}
          <Route
            path="/audit"
            element={
              <RequirePermission permission="list-audit">
                <AuditList />
              </RequirePermission>
            }
          />

          {/* Contenido dinámico */}
          <Route
            path="/content"
            element={
              <RequirePermission permission="list-custom-models">
                <CustomModelsList />
              </RequirePermission>
            }
          />
          <Route
            path="/content/:slug/edit"
            element={
              <RequirePermission permission="edit-custom-models">
                <CustomModelBuilder />
              </RequirePermission>
            }
          />
          <Route
            path="/content/:slug/entries"
            element={
              <RequirePermission permission="list-custom-models">
                <CustomModelEntries />
              </RequirePermission>
            }
          />

          {/* Eventos */}
          <Route
            path="/events"
            element={
              <RequirePermission permission="list-events">
                <EventsList />
              </RequirePermission>
            }
          />
          <Route
            path="/events/bookings"
            element={
              <RequirePermission permission="list-bookings">
                <BookingsList />
              </RequirePermission>
            }
          />

          {/* Tienda */}
          <Route
            path="/shop/products"
            element={
              <RequirePermission permission="list-products">
                <ProductsList />
              </RequirePermission>
            }
          />
          <Route
            path="/shop/orders"
            element={
              <RequirePermission permission="list-orders">
                <OrdersList />
              </RequirePermission>
            }
          />
          <Route
            path="/shop/coupons"
            element={
              <RequirePermission permission="list-coupons">
                <CouponsList />
              </RequirePermission>
            }
          />

          {/* Sistema */}
          <Route
            path="/media"
            element={
              <RequirePermission permission="list-media">
                <MediaLibrary />
              </RequirePermission>
            }
          />
          <Route
            path="/settings"
            element={
              <RequirePermission permission="manage-settings">
                <SettingsPage />
              </RequirePermission>
            }
          />

          {/* Rutas del proyecto (front/src/custom/routes/*.tsx) */}
          {Object.values(customRouteModules)
            .flatMap((mod) => mod.default)
            .map((route) => (
              <Route
                key={route.path}
                path={route.path}
                element={
                  route.permission ? (
                    <RequirePermission permission={route.permission}>
                      {route.element}
                    </RequirePermission>
                  ) : (
                    route.element
                  )
                }
              />
            ))}
        </Route>
      </Route>

      <Route path="/403" element={<ForbiddenPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}

export default function Router() {
  return (
    <Suspense fallback={<PageFallback />}>
      <RouterProvider router={createBrowserRouter([{ path: '*', element: <AppRoutes /> }])} />
    </Suspense>
  )
}
