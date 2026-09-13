import { api, unwrap } from '@/lib/api'
import { createResource, type ApiResource } from '@/services/apiResource'

export interface ProductVariant {
  id?: number
  sku: string
  name?: string | null
  options?: Record<string, string> | null
  price: number | null
  stock: number
  track_stock: boolean
  is_default?: boolean
}

export interface ProductRecord {
  id: number
  slug: string
  title: string
  excerpt: string | null
  description: string | null
  status: string
  sku: string | null
  price_cents: number | null
  compare_at_cents: number | null
  stock: number | null
  track_stock: boolean
  is_featured: boolean
  tax_rate_id: number | null
  published_at: string | null
  variants?: ProductVariant[]
  in_stock?: boolean
}

export interface OrderRecord {
  id: number
  number: string
  email: string
  customer_name?: string | null
  status: string
  payment_status: string
  subtotal_cents: number
  discount_cents: number
  tax_cents: number
  shipping_cents: number
  total_cents: number
  currency: string
  coupon_code: string | null
  shipping_address: Record<string, string> | null
  billing_address: Record<string, string> | null
  placed_at: string | null
  items?: OrderItemRecord[]
  history?: Array<{ id: number; from_status: string | null; to_status: string; note: string | null; created_at: string }>
  allowed_transitions?: string[]
}

export interface OrderItemRecord {
  id: number
  title: string
  sku: string | null
  options: Record<string, string> | null
  quantity: number
  unit_price_cents: number
  total_cents: number
}

export interface CouponRecord {
  id: number
  code: string
  type: 'percentage' | 'fixed'
  percentage: number | null
  amount_cents: number | null
  starts_at: string | null
  ends_at: string | null
  usage_limit: number | null
  usage_count: number
  min_subtotal_cents: number | null
  is_active: boolean
}

export const productsService: ApiResource<ProductRecord> & {
  saveWithVariants: (product: Partial<ProductRecord> & { variants?: ProductVariant[] }) => Promise<ProductRecord>
} = {
  ...createResource<ProductRecord>('/admin/products'),
  saveWithVariants(product) {
    const payload = {
      ...product,
      price_cents: product.price_cents,
      variants: product.variants?.map((variant) => ({
        ...variant,
        price_cents: variant.price !== null && variant.price !== undefined ? Math.round(variant.price * 100) : null,
      })),
    }
    return product.id
      ? api.put<{ data: ProductRecord }>(`/admin/products/${product.id}`, payload).then((r) => unwrap(r))
      : api.post<{ data: ProductRecord }>('/admin/products', payload).then((r) => unwrap(r))
  },
}

export const ordersService = {
  ...createResource<OrderRecord>('/admin/orders'),
  changeStatus: (id: number, status: string, note?: string) =>
    api.patch(`/admin/orders/${id}/status`, { status, note }),
  refund: (id: number, amount?: number) =>
    api.post(`/admin/orders/${id}/refund`, amount ? { amount } : {}),
}

export const couponsService: ApiResource<CouponRecord> & {
  save: (coupon: Partial<CouponRecord>) => Promise<CouponRecord>
} = {
  ...createResource<CouponRecord>('/admin/coupons'),
  save(coupon) {
    return coupon.id
      ? api.put<{ data: CouponRecord }>(`/admin/coupons/${coupon.id}`, coupon).then((r) => unwrap(r))
      : api.post<{ data: CouponRecord }>('/admin/coupons', coupon).then((r) => unwrap(r))
  },
}
