<?php
// Server-side block type registry for the visual page builder (Part 0).

final class BuilderBlockRegistry
{
    /** @var array<string, array> */
    private static array $types = [];

    /**
     * @param array $definition Registry definition per master prompt §8.3.
     * @throws InvalidArgumentException
     */
    public static function register(array $definition): void
    {
        $required = [
            'type' => 'string',
            'label' => 'string',
            'icon' => 'string',
            'category' => 'string',
            'allows_children' => 'boolean',
            'min_children' => 'integer',
            'max_children' => 'integer',
            'supports_text_formatting' => 'boolean',
            'allowed_child_types' => 'array',
            'allowed_parent_types' => 'array',
            'default_data' => 'array',
        ];

        foreach ($required as $key => $phpType) {
            if (!array_key_exists($key, $definition)) {
                throw new InvalidArgumentException('Missing required registry key: ' . $key);
            }
            $actual = gettype($definition[$key]);
            if ($actual !== $phpType) {
                throw new InvalidArgumentException('Registry key "' . $key . '" must be of type ' . $phpType);
            }
        }

        $type = $definition['type'];
        if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_]{1,49}$/', $type)) {
            throw new InvalidArgumentException('Invalid block type name');
        }

        if (isset(self::$types[$type])) {
            throw new InvalidArgumentException('Block type already registered: ' . $type);
        }

        if ($definition['category'] !== 'structure' && $definition['category'] !== 'content') {
            throw new InvalidArgumentException('Invalid block category');
        }

        $allowsChildren = $definition['allows_children'];
        $minChildren = $definition['min_children'];
        $maxChildren = $definition['max_children'];

        if ($minChildren < 0 || $maxChildren < 0) {
            throw new InvalidArgumentException('min_children and max_children must be >= 0');
        }

        if ($allowsChildren === false && ($minChildren !== 0 || $maxChildren !== 0)) {
            throw new InvalidArgumentException('A type with allows_children=false must have min_children=0 and max_children=0');
        }

        if ($maxChildren !== 0 && $maxChildren < $minChildren) {
            throw new InvalidArgumentException('max_children must not be smaller than min_children');
        }

        foreach (['allowed_child_types', 'allowed_parent_types'] as $listKey) {
            $list = $definition[$listKey];
            foreach ($list as $entry) {
                if (!is_string($entry) || !preg_match('/^[a-z][a-z0-9_]{1,49}$/', $entry)) {
                    throw new InvalidArgumentException('Invalid entry in ' . $listKey);
                }
            }
        }

        if ($allowsChildren === false && count($definition['allowed_child_types']) > 0) {
            throw new InvalidArgumentException('A type with allows_children=false must have an empty allowed_child_types');
        }

        if (array_key_exists('sanitize', $definition) && $definition['sanitize'] !== null && !is_callable($definition['sanitize'])) {
            throw new InvalidArgumentException('sanitize must be callable or null');
        }

        if (array_key_exists('extract_media', $definition) && $definition['extract_media'] !== null && !is_callable($definition['extract_media'])) {
            throw new InvalidArgumentException('extract_media must be callable or null');
        }

        // H7: every leaf type (allows_children=false) holds user content and MUST define a sanitize hook.
        if ($allowsChildren === false && (!array_key_exists('sanitize', $definition) || $definition['sanitize'] === null)) {
            throw new InvalidArgumentException('A type with allows_children=false must define a sanitize callable');
        }

        // Referenced types in allowed_child_types / allowed_parent_types are
        // intentionally NOT checked here: types load from a glob in
        // alphabetical order, so a definition may reference a type that
        // registers later in the same bootstrap.

        self::$types[$type] = $definition;
    }

    public static function get(string $type): ?array
    {
        return self::$types[$type] ?? null;
    }

    /** @return array<string, array> */
    public static function all(): array
    {
        return self::$types;
    }

    /** @return array<string, array> */
    public static function insertable(): array
    {
        $out = [];
        foreach (self::$types as $type => $definition) {
            if ($definition['category'] === 'content') {
                $out[$type] = $definition;
            }
        }
        return $out;
    }

    public static function exists(string $type): bool
    {
        return isset(self::$types[$type]);
    }

    /**
     * Runs the type's sanitize hook. Hook signature: function (stdClass $data, PDO $pdo): stdClass.
     * The hook may throw InvalidArgumentException to reject the data (save.php answers 400).
     * @throws InvalidArgumentException
     */
    public static function sanitizeData(string $type, stdClass $data, PDO $pdo): stdClass
    {
        $definition = self::$types[$type] ?? null;
        if ($definition === null || empty($definition['sanitize'])) {
            return $data;
        }
        $result = $definition['sanitize']($data, $pdo);
        if (!($result instanceof stdClass)) {
            throw new InvalidArgumentException('sanitize must return a stdClass');
        }
        return $result;
    }

    /**
     * Runs the type's extract_media hook. Hook signature: function (stdClass $data): int[] (media_library ids).
     * @return int[] unique positive ids
     * @throws InvalidArgumentException
     */
    public static function extractMedia(string $type, stdClass $data): array
    {
        $definition = self::$types[$type] ?? null;
        if ($definition === null || empty($definition['extract_media'])) {
            return [];
        }
        $ids = $definition['extract_media']($data);
        if (!is_array($ids)) {
            throw new InvalidArgumentException('extract_media must return an array');
        }
        $unique = [];
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                throw new InvalidArgumentException('extract_media must return positive integers');
            }
            $unique[$id] = $id;
        }
        return array_values($unique);
    }

    public static function defaultData(string $type): stdClass
    {
        if (!isset(self::$types[$type])) {
            return new stdClass();
        }
        $default = self::$types[$type]['default_data'];
        if (empty($default)) {
            return new stdClass();
        }
        return (object) $default;
    }
}
