<?php

declare(strict_types=1);

namespace HelloFigma;

defined('ABSPATH') || exit;

/**
 * Lightweight JSON normalization and schema validation for Elementor data.
 *
 * Normalization:
 *   - Ensures every element's 'settings' is a JSON object (stdClass), not an array.
 *   - Ensures 'elements' / 'content' are sequential arrays, not objects.
 *   - Fills in missing structural keys with safe defaults.
 *
 * Validation:
 *   - Template-level checks: title, type, version, content, page_settings.
 *     These are CRITICAL — missing any one means the template cannot be saved.
 *   - Element-level checks: id, elType, settings, elements, widgetType.
 *     These are WARNINGS — Elementor is lenient and can auto-fill some gaps.
 *
 * @see https://developers.elementor.com/docs/data-structure/page-content/
 */
class JsonNormalizer
{
    /**
     * Normalize a single element tree recursively.
     *
     * @param array $element Raw element array
     * @return array Normalized element with correct types
     */
    public static function normalize_element(array $element): array
    {
        // ── Ensure 'settings' is a JSON object ──
        if (!isset($element['settings']) || !($element['settings'] instanceof \stdClass)) {
            $raw = $element['settings'] ?? [];
            $element['settings'] = self::to_object($raw);
        } else {
            // Walk existing settings and ensure nested objects stay as objects
            $element['settings'] = self::normalize_settings_object($element['settings']);
        }

        // ── Ensure 'elements' is a sequential array ──
        if (!isset($element['elements']) || !is_array($element['elements'])) {
            $element['elements'] = [];
        } else {
            // Re-index to avoid sparse / associative issues
            $element['elements'] = array_values($element['elements']);
        }

        // ── Ensure 'id' exists ──
        if (!isset($element['id']) || !is_string($element['id'])) {
            $element['id'] = substr(bin2hex(random_bytes(4)), 0, 8);
        }

        // ── Ensure 'elType' — default to container if missing ──
        if (!isset($element['elType']) || !in_array($element['elType'], ['container', 'widget', 'section'], true)) {
            $element['elType'] = 'container';
        }

        // ── Ensure 'isInner' ──
        if (!isset($element['isInner'])) {
            $element['isInner'] = false;
        }

        // ── Recurse into children ──
        foreach ($element['elements'] as $i => $child) {
            if (is_array($child)) {
                $element['elements'][$i] = self::normalize_element($child);
            }
        }

        return $element;
    }

    /**
     * Normalize a full template array.
     *
     * @param array $template Raw template array
     * @return array Normalized template
     */
    public static function normalize_template(array $template): array
    {
        // Ensure required top-level keys
        if (!isset($template['title']) || !is_string($template['title'])) {
            $template['title'] = 'Untitled';
        }
        if (!isset($template['type']) || !is_string($template['type'])) {
            $template['type'] = 'page';
        }
        if (!isset($template['version']) || !is_string($template['version'])) {
            $template['version'] = '0.4';
        }
        if (!isset($template['page_settings']) || !is_array($template['page_settings'])) {
            $template['page_settings'] = [];
        }

        // Normalize each top-level content element
        if (isset($template['content']) && is_array($template['content'])) {
            $template['content'] = array_values($template['content']);
            foreach ($template['content'] as $i => $element) {
                if (is_array($element)) {
                    $template['content'][$i] = self::normalize_element($element);
                }
            }
        } else {
            $template['content'] = [];
        }

        return $template;
    }

    /**
     * Validate a template structure.
     *
     * Template-level failures (title, type, version, content, page_settings)
     * are CRITICAL — missing any one means the template cannot be safely saved.
     * Element-level failures (id, elType, settings, widgetType) are warnings;
     * the template can still be saved but may need manual fixes in Elementor.
     *
     * @param array $template The template to validate
     * @return array{ok: bool, errors: string[]} ok=false if any critical error
     */
    public static function validate_template(array $template): array
    {
        $result = ['ok' => true, 'errors' => []];

        // ── Template-level checks (CRITICAL) ──
        if (!isset($template['title']) || !is_string($template['title'])) {
            $result['errors'][] = "Missing or invalid 'title' (expected string)";
        }
        if (!isset($template['type']) || !is_string($template['type'])) {
            $result['errors'][] = "Missing or invalid 'type' (expected string)";
        }
        if (!isset($template['version']) || !is_string($template['version'])) {
            $result['errors'][] = "Missing or invalid 'version' (expected string)";
        }
        if (!isset($template['page_settings']) || !is_array($template['page_settings'])) {
            $result['errors'][] = "Missing or invalid 'page_settings' (expected array)";
        }

        // ── Content element checks (WARNINGS per element) ──
        if (!isset($template['content']) || !is_array($template['content'])) {
            $result['errors'][] = "Missing or invalid 'content' (expected array)";
        } else {
            foreach ($template['content'] as $i => $element) {
                if (is_array($element)) {
                    $element_errors = self::validate_element($element, "content[{$i}]");
                    $result['errors'] = array_merge($result['errors'], $element_errors);
                }
            }
        }

        // ── Determine ok flag — any template-level error is critical ──
        // Element-level errors do not flip ok to false (they are warnings).
        $template_keys = ['title', 'type', 'version', 'content', 'page_settings'];
        foreach ($result['errors'] as $error) {
            foreach ($template_keys as $key) {
                if (str_starts_with($error, "Missing or invalid '{$key}'")) {
                    $result['ok'] = false;
                    break 2;
                }
            }
        }

        return $result;
    }

    /**
     * Validate a single element recursively.
     *
     * Element-level failures are WARNINGS — the template can still be saved
     * but may need manual fixes in Elementor (it auto-fills some gaps).
     *
     * @param array  $element Element data
     * @param string $path    Dot-notation path for error messages
     * @return string[] Validation warnings
     */
    private static function validate_element(array $element, string $path = ''): array
    {
        $errors = [];

        if (!isset($element['id']) || !is_string($element['id'])) {
            $errors[] = "{$path}: Missing or invalid 'id' (expected string)";
        }
        if (!isset($element['elType']) || !in_array($element['elType'], ['container', 'widget', 'section'], true)) {
            $errors[] = "{$path}: Missing or invalid 'elType' (expected container/widget/section)";
        }
        if (!isset($element['settings'])) {
            $errors[] = "{$path}: Missing 'settings' key";
        } elseif (!($element['settings'] instanceof \stdClass) && !is_array($element['settings'])) {
            $errors[] = "{$path}: 'settings' must be an object or array";
        }
        if (!isset($element['elements']) || !is_array($element['elements'])) {
            $errors[] = "{$path}: Missing or invalid 'elements' (expected array)";
        } elseif (!empty($element['elements'])) {
            foreach ($element['elements'] as $j => $child) {
                if (is_array($child)) {
                    $errors = array_merge($errors, self::validate_element($child, "{$path}.elements[{$j}]"));
                }
            }
        }
        if (!isset($element['isInner']) || !is_bool($element['isInner'])) {
            $errors[] = "{$path}: Missing or invalid 'isInner' (expected bool)";
        }
        // When elType === 'widget', widgetType is required
        if (($element['elType'] ?? '') === 'widget' && (empty($element['widgetType']) || !is_string($element['widgetType']))) {
            $errors[] = "{$path}: elType is 'widget' but 'widgetType' is missing or invalid (expected non-empty string)";
        }

        return $errors;
    }

    /**
     * Recursively convert a mixed value to a JSON-safe object,
     * preserving numeric-indexed arrays as arrays.
     */
    private static function to_object($value): \stdClass
    {
        if ($value instanceof \stdClass) {
            return $value;
        }

        if (is_array($value)) {
            // Check if this is a sequential (numeric) array → keep as array
            // by wrapping it in an object property. But for settings, even
            // sequential arrays are valid (e.g., carousel slides).
            // We convert associative arrays to objects recursively.
            $is_list = array_is_list($value);
            if ($is_list) {
                // Convert list items recursively if they are arrays
                $result = new \stdClass();
                foreach ($value as $k => $v) {
                    if (is_array($v) || $v instanceof \stdClass) {
                        $result->$k = self::to_object($v);
                    } else {
                        $result->$k = $v;
                    }
                }
                return $result;
            }

            // Associative array → stdClass
            $result = new \stdClass();
            foreach ($value as $k => $v) {
                if (is_array($v) || $v instanceof \stdClass) {
                    $result->$k = self::to_object($v);
                } else {
                    $result->$k = $v;
                }
            }
            return $result;
        }

        return new \stdClass();
    }

    /**
     * Recursively normalize a settings object, ensuring nested values
     * that should be objects are not accidentally arrays.
     */
    private static function normalize_settings_object(\stdClass $settings): \stdClass
    {
        foreach (get_object_vars($settings) as $key => $value) {
            if ($value instanceof \stdClass) {
                // Already an object — good, recurse
                $settings->$key = self::normalize_settings_object($value);
            } elseif (is_array($value)) {
                // An array — check if it's a list of objects (like carousel slides)
                // or an associative array that should be an object
                $settings->$key = self::to_object($value);
            }
            // Scalars are fine as-is
        }
        return $settings;
    }
}
