<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Definición de un campo de contenido dinámico (el "schema" lógico). */
class CustomModelField extends Model
{
    protected $fillable = [
        'custom_model_id', 'name', 'label', 'type', 'options', 'rules',
        'default_value', 'is_required', 'is_unique', 'is_searchable',
        'is_filterable', 'is_sortable', 'show_in_list', 'show_in_form', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'json',
            'rules' => 'json',
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'is_sortable' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_form' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function customModel(): BelongsTo
    {
        return $this->belongsTo(CustomModel::class);
    }

    public function typeEnum(): CustomFieldType
    {
        return CustomFieldType::from($this->type);
    }

    /** Reglas Laravel de validación (declarativas, en rotary no existían). */
    public function validationRules(): array
    {
        $rules = $this->rules ?? [];

        if ($this->is_unique) {
            $rules[] = 'unique:'.$this->customModel->table_name.','.$this->name;
        }

        return $rules;
    }
}
