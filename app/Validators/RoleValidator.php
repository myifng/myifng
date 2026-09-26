<?php
declare(strict_types=1);

namespace App\Validators;

final class RoleValidator
{
    public const LABELS = ['name' => 'रोल का नाम', 'slug' => 'स्लग', 'description' => 'विवरण', 'level' => 'स्तर'];

    public static function rules(?int $id = null): array
    {
        return [
            'name' => 'required|max:80',
            'slug' => 'required|slug|max:80|unique:roles,slug' . ($id ? ",$id" : ''),
            'description' => 'nullable|max:255',
            'level' => 'required|integer|min:1|max:99',
        ];
    }
}
