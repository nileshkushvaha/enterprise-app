<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Converts raw permission names into human-readable labels.
 *
 * Supported formats:
 *   "Action:Module"           → action label: "Action"     module label: "Module"
 *   "module.action_name"      → action label: "Action Name" module label: "Module"
 *   "module.action-name"      → action label: "Action Name" module label: "Module"
 */
final class PermissionLabelFormatter
{
    /**
     * Extract and humanise the module (group) segment from a permission name.
     *
     * "Create:Country"          → "Country"
     * "assessment.travel_desk"  → "Assessment"
     */
    public static function moduleLabel(string $permissionName): string
    {
        // Check config override first
        $module = self::extractModule($permissionName);
        $config = config("permission-ui.{$module}");

        if ($config && isset($config['title'])) {
            return $config['title'];
        }

        return self::humanise($module);
    }

    /**
     * Extract and humanise the action segment from a permission name.
     *
     * "Create:Country"              → "Create"
     * "DeleteAny:User"              → "Delete Any"
     * "ForceDeleteAny:User"         → "Force Delete Any"
     * "assessment.travel_desk"      → "Travel Desk"
     * "assessment.faculty_declaration_forms" → "Faculty Declaration Forms"
     */
    public static function actionLabel(string $permissionName): string
    {
        return self::humanise(self::extractAction($permissionName));
    }

    /**
     * Extract the raw module key (before humanising).
     *
     * "Create:Country"         → "Country"
     * "assessment.travel_desk" → "assessment"
     */
    public static function extractModule(string $permissionName): string
    {
        if (str_contains($permissionName, ':')) {
            // "Action:Module" format
            return trim(explode(':', $permissionName, 2)[1] ?? $permissionName);
        }

        // "module.action" format
        return trim(explode('.', $permissionName, 2)[0] ?? $permissionName);
    }

    /**
     * Extract the raw action key (before humanising).
     *
     * "Create:Country"         → "Create"
     * "assessment.travel_desk" → "travel_desk"
     */
    public static function extractAction(string $permissionName): string
    {
        if (str_contains($permissionName, ':')) {
            return trim(explode(':', $permissionName, 2)[0] ?? $permissionName);
        }

        $parts = explode('.', $permissionName, 2);
        return trim($parts[1] ?? $parts[0] ?? $permissionName);
    }

    /**
     * Convert any casing convention to a human-readable title.
     *
     * "DeleteAny"                    → "Delete Any"
     * "ForceDeleteAny"               → "Force Delete Any"
     * "travel_desk"                  → "Travel Desk"
     * "faculty_declaration_forms"    → "Faculty Declaration Forms"
     * "travel-desk"                  → "Travel Desk"
     */
    public static function humanise(string $value): string
    {
        // CamelCase → insert spaces before uppercase letters
        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value) ?? $value;

        // Replace underscores and hyphens with spaces
        $value = str_replace(['_', '-', '.'], ' ', $value);

        // Collapse multiple spaces and title-case
        return Str::title(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    /**
     * Get the display icon for a module (from config).
     * Falls back to a generic icon if not configured.
     */
    public static function moduleIcon(string $module): string
    {
        return config("permission-ui.{$module}.icon", 'heroicon-o-square-3-stack-3d');
    }

    /**
     * Get the sort order for a module (from config).
     * Unconfigured modules get a high order number so they appear last.
     */
    public static function moduleOrder(string $module): int
    {
        return (int) config("permission-ui.{$module}.order", 999);
    }
}
