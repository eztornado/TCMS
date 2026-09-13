import { api, unwrap } from '@/lib/api'
import { createResource, type ApiResource } from '@/services/apiResource'

export interface EventSession {
  id?: number
  title?: string | null
  starts_at: string | null
  ends_at: string | null
  capacity: number | null
  price_cents: number | null
  sale_starts_at?: string | null
  sale_ends_at?: string | null
  status: string
}

export interface EventRecord {
  id: number
  slug: string
  title: string
  excerpt: string | null
  description: string | null
  status: string
  venue: string | null
  address: string | null
  city: string | null
  capacity: number | null
  price_cents: number | null
  currency: string
  is_featured: boolean
  published_at: string | null
  sessions?: EventSession[]
  seats_left?: number | null
}

export interface BookingRecord {
  id: number
  reference: string
  event_id?: number
  event_title?: string
  session_starts_at?: string | null
  customer_name: string
  customer_email: string
  customer_phone?: string | null
  seats: number
  amount_cents: number
  currency: string
  status: string
  payment_status?: string
  created_at: string
}

export const eventsService: ApiResource<EventRecord> & {
  saveWithSessions: (
    event: Partial<EventRecord> & { sessions?: EventSession[] },
  ) => Promise<EventRecord>
} = {
  ...createResource<EventRecord>('/admin/events'),

  /** Guarda evento + sesiones en una sola llamada. */
  saveWithSessions(event) {
    const payload = { ...event }
    return event.id
      ? api.put<{ data: EventRecord }>(`/admin/events/${event.id}`, payload).then((r) => unwrap(r))
      : api.post<{ data: EventRecord }>('/admin/events', payload).then((r) => unwrap(r))
  },
}

export const bookingsService: ApiResource<BookingRecord> & {
  updateStatus: (id: number, status: string) => Promise<unknown>
} = {
  ...createResource<BookingRecord>('/admin/bookings'),
  updateStatus(id, status) {
    return api.put(`/admin/bookings/${id}`, { status })
  },
}
