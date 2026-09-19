<?php

namespace App\Services\CustomModels;

use App\Enums\CustomFieldType;
use App\Exceptions\AppException;
use App\Models\CustomModel;
use App\Models\CustomModelField;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sincroniza la definición de un Custom Model con su tabla física.
 *
 * Es el equivalente seguro del "generador de tablas" de rotary: usa el Schema
 * builder de Laravel (nunca SQL crudo), valida los identificadores contra una
 * whitelist estricta y crea índices para campos filtrables/únicos.
 */
class SchemaService
{
    /** Columnas base de toda tabla de Custom Model. */
    private const RESERVED = [
        'id', 'uuid', 'user_id', 'status', 'published_at', 'slug', 'created_at',
        'updated_at', 'deleted_at', 'terms', 'media',
    ];

    private const NAME_PATTERN = '/^[a-z][a-z0-9_]{1,62}$/';

    public function createTable(CustomModel $model): void
    {
        if (Schema::hasTable($model->table_name)) {
            throw new AppException(
                AppException::SCHEMA_CONFLICT,
                "La tabla [{$model->table_name}] ya existe.",
                422,
            );
        }

        Schema::create($model->table_name, function ($table): void {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();              // identidad de sync
            $table->foreignId('user_id')->nullable()->index();       // autor
            $table->string('status')->default('draft')->index();     // editorial
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        foreach ($model->fields as $field) {
            $this->addColumn($model, $field);
        }

        $this->addSlugsAndIndexes($model);
    }

    public function dropTable(CustomModel $model): void
    {
        Schema::dropIfExists($model->table_name);
    }

    /**
     * Añade una columna (y su índice si procede) para un campo nuevo.
     * Devuelve el nombre de la columna DDL creada.
     */
    public function addColumn(CustomModel $model, CustomModelField $field): string
    {
        $this->assertValidName($field->name);

        Schema::table($model->table_name, function ($table) use ($field): void {
            $column = $this->definitionToColumn($table, $field->name, $field->type);
            if ($field->is_unique) {
                $table->unique($field->name);
            }
            if ($field->is_filterable && in_array($field->type, [CustomFieldType::Select, CustomFieldType::Boolean, CustomFieldType::Number], true)) {
                $table->index($field->name);
            }
        });

        return $field->name;
    }

    /**
     * Tipo de campo → definición de columna física. Todo nullable: el
     * validador decide la obligatoriedad, el esquema no bloquea datos
     * históricos al cambiar de opinión.
     */
    private function definitionToColumn($table, string $name, string|CustomFieldType $type)
    {
        $type = $type instanceof CustomFieldType ? $type : CustomFieldType::from($type);

        return match ($type) {
            CustomFieldType::Number => $table->unsignedBigInteger($name)->nullable(),
            CustomFieldType::Decimal => $table->decimal($name, 12, 2)->nullable(),
            CustomFieldType::Boolean => $table->boolean($name)->nullable(),
            CustomFieldType::Date => $table->date($name)->nullable(),
            CustomFieldType::DateTime => $table->dateTime($name)->nullable(),
            CustomFieldType::Textarea, CustomFieldType::RichText => $table->text($name)->nullable(),
            CustomFieldType::MultiSelect, CustomFieldType::Json => $table->json($name)->nullable(),
            // Text, Slug, Color, Select, Relation y Media: cadenas.
            default => $table->string($name)->nullable(),
        };
    }

    public function dropColumn(CustomModel $model, string $name): void
    {
        $this->assertValidName($name);

        Schema::table($model->table_name, function ($table) use ($name): void {
            $table->dropColumn($name);
        });
    }

    /** Validación de identificadores: patrón + lista negra + longitud. */
    public function assertValidName(string $name): void
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            throw new AppException(
                AppException::SCHEMA_CONFLICT,
                "Identificador de campo inválido: [{$name}]. Usa minúsculas, números y guiones bajos (3-63).",
                422,
            );
        }

        if (in_array($name, self::RESERVED, true)) {
            throw new AppException(
                AppException::SCHEMA_CONFLICT,
                "El nombre de campo [{$name}] está reservado por el sistema.",
                422,
            );
        }
    }

    public function tableExists(string $tableName): bool
    {
        return Schema::hasTable($tableName);
    }

    /** Crea slug único de modelo y su nombre de tabla derivado. */
    public static function makeTableName(string $slug): string
    {
        return 'cm_'.Str::slug($slug, '_');
    }

    /**
     * Añade columna slug con índice único compuesto (slug + deleted_at
     * no aplicable en SQLite simple) para modelos con campo de tipo slug.
     */
    private function addSlugsAndIndexes(CustomModel $model): void
    {
        $slugFields = $model->fields->filter(fn (CustomModelField $f) => $f->type === CustomFieldType::Slug);

        foreach ($slugFields as $field) {
            Schema::table($model->table_name, function ($table) use ($field): void {
                $table->unique($field->name);
            });
        }
    }
}
