<?php

namespace App\Enums;

/**
 * Tipos de campo del constructor de contenido dinámico. Cada tipo know how
 * renderizarse en el panel (el front mapea tipo → widget) y cómo
 * materializarse en la tabla física (CustomModelSchema::columnType).
 */
enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'richtext';
    case Number = 'number';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Media = 'media';
    case Relation = 'relation';
    case Slug = 'slug';
    case Color = 'color';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Textarea => 'Texto largo',
            self::RichText => 'Texto enriquecido',
            self::Number => 'Número',
            self::Decimal => 'Decimal',
            self::Boolean => 'Sí/No',
            self::Date => 'Fecha',
            self::DateTime => 'Fecha y hora',
            self::Select => 'Selección',
            self::MultiSelect => 'Selección múltiple',
            self::Media => 'Imagen',
            self::Relation => 'Relación',
            self::Slug => 'Slug (URL)',
            self::Color => 'Color',
            self::Json => 'JSON',
        };
    }

    public function isMultiple(): bool
    {
        return in_array($this, [self::MultiSelect], true);
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        return self::cases();
    }
}
