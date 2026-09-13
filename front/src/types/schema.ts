/** Tipos del esquema de custom models que envía el backend (`GET /cm/{slug}/schema`). */

export type FieldType =
  | 'text'
  | 'textarea'
  | 'richtext'
  | 'number'
  | 'decimal'
  | 'boolean'
  | 'date'
  | 'datetime'
  | 'select'
  | 'multiselect'
  | 'media'
  | 'relation'
  | 'slug'
  | 'color'
  | 'json'

export interface FieldSchema {
  id: number
  name: string
  label: string
  type: FieldType
  options: {
    choices?: Record<string, string>
    /** Para relaciones: slug del modelo destino. */
    related?: string
    [key: string]: unknown
  } | null
  rules: string[] | null
  default_value: string | null
  is_required: boolean
  is_unique: boolean
  is_searchable: boolean
  is_filterable: boolean
  is_sortable: boolean
  show_in_list: boolean
  show_in_form: boolean
}

export interface TaxonomySchema {
  id: number
  name: string
  slug: string
  is_hierarchical: boolean
}

/** Contrato de UI que devuelve el backend (plano, sin anidar `model`). */
export interface CustomModelSchema {
  slug: string
  label: string
  plural_label: string | null
  icon: string
  per_page: number
  show_field: string
  has_status: boolean
  is_taxonomizable: boolean
  fields: FieldSchema[]
  taxonomies: TaxonomySchema[]
}

export interface CustomModelDefinition {
  id: number
  slug: string
  label: string
  plural_label: string | null
  icon: string
  table_name: string
  per_page: number
  show_field: string
  has_status: boolean
  is_taxonomizable: boolean
}

/** Entrada genérica: cualquier clave/valor según el esquema del modelo. */
export type EntryRecord = Record<string, unknown> & { id: number | string }
