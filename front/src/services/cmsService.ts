import { api, unwrap } from '@/lib/api'
import { createResource, type ApiResource } from '@/services/apiResource'
import type { EntryRecord, CustomModelDefinition, CustomModelSchema, FieldSchema } from '@/types/schema'

/** Payload de campo tal y como lo valida el backend. */
export interface FieldPayload {
  id?: number
  name: string
  label: string
  type: FieldSchema['type']
  options?: FieldSchema['options']
  rules?: string[] | null
  default_value?: string | null
  is_required?: boolean
  is_unique?: boolean
  is_searchable?: boolean
  is_filterable?: boolean
  is_sortable?: boolean
  show_in_list?: boolean
  show_in_form?: boolean
  sort?: number
}

/** Servicios del motor de contenido dinámico. */
export const cmsService = {
  /** Catálogo de modelos definidos. */
  models(): Promise<CustomModelDefinition[]> {
    return api.get<{ data: CustomModelDefinition[] }>('/admin/models').then((r) => unwrap(r))
  },

  createModel(payload: Partial<CustomModelDefinition> & { slug: string; label: string }): Promise<CustomModelDefinition> {
    return api.post<{ data: CustomModelDefinition }>('/admin/models', payload).then((r) => unwrap(r))
  },

  updateModel(slug: string, payload: Partial<CustomModelDefinition>): Promise<CustomModelDefinition> {
    return api.put<{ data: CustomModelDefinition }>(`/admin/models/${slug}`, payload).then((r) => unwrap(r))
  },

  deleteModel(slug: string): Promise<void> {
    return api.delete(`/admin/models/${slug}`)
  },

  /** Esquema completo (modelo + campos): contrato de UI del builder y las entradas. */
  schema(slug: string): Promise<CustomModelSchema> {
    return api.get<{ data: CustomModelSchema }>(`/cm/${slug}/schema`).then((r) => unwrap(r))
  },

  /**
   * Sincroniza el conjunto de campos del builder con el backend. El backend
   * gestiona campos uno a uno (create/update/delete), así que se calcula el
   * diff contra la definición previa: nuevos se crean, existentes se
   * actualizan (label/flags/orden) y los ausentes se eliminan.
   */
  async saveFields(
    slug: string,
    fields: FieldPayload[],
    previousIds: number[] = [],
  ): Promise<void> {
    const kept = new Set<number>()

    for (const [index, field] of fields.entries()) {
      const { id, ...payload } = { ...field, sort: index }
      if (id) {
        kept.add(id)
        await api.put(`/admin/fields/${id}`, payload)
      } else {
        const created = await api
          .post<{ data: { id: number } }>(`/admin/models/${slug}/fields`, payload)
          .then((r) => unwrap(r))
        kept.add(created.id)
      }
    }

    for (const id of previousIds.filter((id) => !kept.has(id))) {
      await api.delete(`/admin/fields/${id}`)
    }
  },

  /** CRUD de entradas por slug. */
  entries(slug: string): ApiResource<EntryRecord> {
    return createResource<EntryRecord>(`/cm/${slug}`)
  },
}
