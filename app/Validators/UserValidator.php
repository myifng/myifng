<?php
declare(strict_types=1);

namespace App\Validators;

/** यूज़र फ़ॉर्म के नियम (नया और बदलाव दोनों) */
final class UserValidator
{
    public const LABELS = [
        'name' => 'नाम', 'email' => 'ईमेल', 'mobile' => 'मोबाइल', 'role_id' => 'रोल', 'status' => 'स्थिति',
        'password' => 'पासवर्ड', 'bio' => 'परिचय', 'current_password' => 'मौजूदा पासवर्ड',
    ];

    public static function rules(?int $id = null): array
    {
        return [
            'name' => 'required|max:120',
            'email' => 'required|email|max:190|unique:users,email' . ($id ? ",$id" : ''),
            'mobile' => 'nullable|mobile',
            'role_id' => 'required|integer|exists:roles,id',
            'status' => 'required|in:active,inactive,suspended',
            'password' => $id ? 'nullable|password|confirmed' : 'required|password|confirmed',
            'bio' => 'nullable|max:500',
        ];
    }

    public static function profileRules(int $id): array
    {
        return [
            'name' => 'required|max:120',
            'email' => "required|email|max:190|unique:users,email,$id",
            'mobile' => 'nullable|mobile',
            'bio' => 'nullable|max:500',
        ];
    }

    public static function passwordRules(): array
    {
        return ['current_password' => 'required', 'password' => 'required|password|confirmed'];
    }
}
