<?php

namespace App\Services\Cms;

use App\Enums\CustomFieldType;
use App\Models\CustomModel;
use App\Models\CustomModelField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materializa la definición de un custom model como tabla física (esquema
 * activo). El builder añade/quita columnas reales; los datos nunca viven en
 * tablas EAV, de modo que filtrar y ordenar es SQL puro.
 */
class CustomModelSchema
{
    /** Tipo de columna SQL para cada tipo de campo lógico. */
    public static function columnType(CustomFieldType $type): \Closure
    {
        return match ($type) {
            CustomFieldType::Text, CustomFieldType::Slug, CustomFieldType::Color,
            CustomFieldType::Select => fn (Blueprint $t, string $c) => $t->string($c)->nullable(),
            CustomFieldType::Textarea, CustomFieldType::RichText, CustomFieldType::Json,
            CustomFieldType::MultiSelect => fn (Blueprint $t, string $c) => $t->text($c)->nullable(),
            CustomFieldType::Number => fn (Blueprint $t, string $c) => $t->bigInteger($c)->nullable(),
            CustomFieldType::Decimal => fn (Blueprint $t, string $c) => $t->decimal($c, 12, 2)->nullable(),
            CustomFieldType::Boolean => fn (Blueprint $t, string $c) => $t->boolean($c)->nullable(),
            CustomFieldType::Date => fn (Blueprint $t, string $c) => $t->date($c)->nullable(),
            CustomFieldType::DateTime => fn (Blueprint $t, string $c) => $t->dateTime($c)->nullable(),
            CustomFieldType::Media => fn (Blueprint $t, string $c) => $t->unsignedBigInteger($c)->nullable(),
            CustomFieldType::Relation => fn (Blueprint $t, string $c) => $t->unsignedBigInteger($c)->nullable(),
        };
    }

    public function create(CustomModel $customModel): void
    {
        Schema::create($customModel->table_name, function (Blueprint $table) use ($customModel) {
            $table->id();
            foreach ($customModel->fields as $field) {
                $this->addColumn($table, $field);
            }
            if ($customModel->has_status) {
                $table->string('status')->default('draft')->index();
                $table->timestamp('published_at')->nullable();
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function drop(CustomModel $customModel): void
    {
        Schema::dropIfExists($customModel->table_name);
    }

    /** Aplica altas/bajas/cambios de campos sobre la tabla física. */
    public function syncField(CustomModelField $field): void
    {
        $table = $field->customModel->table_name;

        if (! Schema::hasColumn($table, $field->name)) {
            Schema::table($table, fn (Blueprint $t) => $this->addColumn($t, $field));

            return;
        }

        // El cambio de tipo de columna es manual por diseño: documentado en
        // docs/CUSTOM-MODELS.md (evita destrucción de datos accidentales).
    }

    public function removeField(CustomModelField $field): void
    {
        $table = $field->customModel->table_name;

        if (Schema::hasColumn($table, $field->name)) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn($field->name));
        }
    }

    protected function addColumn(Blueprint $table, CustomModelField $field): void
    {
        $closure = self::columnType($field->typeEnum());
        $closure($table, $field->name);

        if ($field->is_unique) {
            $table->unique($field->name);
        }
        if ($field->is_searchable) {
            $table->index($field->name);
        }
    }
}
