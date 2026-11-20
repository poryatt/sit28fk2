<?php
// Tree validator for the visual page builder (Part 0).
// Validates a posted block tree against the registry and the limits in §11.
// Iterative (explicit stack) so a deeply nested payload hits rule 9
// instead of blowing the PHP call stack.

final class BuilderTreeValidator
{
    private const MAX_BLOCKS = 500;
    private const MAX_DEPTH = 8;
    private const MAX_DATA_BYTES = 65536;

    /** @param array $tree PHP array of stdClass nodes. @return string[] Persian error strings. */
    public static function validate(array $tree): array
    {
        $errors = [];

        // Rule 1: root is exactly [header, body, footer] in that order.
        $rootOk = count($tree) === 3;
        if ($rootOk) {
            $expected = ['header', 'body', 'footer'];
            foreach ($tree as $i => $node) {
                if (!($node instanceof stdClass) || !isset($node->type) || $node->type !== $expected[$i]) {
                    $rootOk = false;
                    break;
                }
            }
        }
        if (!$rootOk) {
            $errors[] = 'ساختار ریشه باید دقیقاً شامل هدر، بدنه و فوتر باشد';
        }

        // Iterative depth-first pre-order traversal with an explicit stack.
        // Each frame: ['node' => mixed, 'parentType' => ?string, 'depth' => int].
        $seenUids = [];
        $nodeCount = 0;
        $maxDepthSeen = 0;

        $stack = [];
        for ($i = count($tree) - 1; $i >= 0; $i--) {
            $stack[] = ['node' => $tree[$i], 'parentType' => null, 'depth' => 1, 'isRoot' => true];
        }

        while (!empty($stack)) {
            $frame = array_pop($stack);
            $node = $frame['node'];
            $parentType = $frame['parentType'];
            $depth = $frame['depth'];
            $isRoot = $frame['isRoot'];

            // Rule 2: node shape.
            if (!($node instanceof stdClass)
                || !property_exists($node, 'uid') || !is_string($node->uid)
                || !property_exists($node, 'type') || !is_string($node->type)
                || !property_exists($node, 'data') || !($node->data instanceof stdClass)
                || !property_exists($node, 'children') || !is_array($node->children)
            ) {
                if (count($errors) < 100) {
                    $errors[] = 'ساختار ارسالی نامعتبر است';
                }
                continue;
            }

            $nodeCount++;
            if ($depth > $maxDepthSeen) {
                $maxDepthSeen = $depth;
            }

            // Rule 9: depth limit, checked as we descend.
            if ($depth > self::MAX_DEPTH) {
                $errors[] = 'عمق تودرتویی بیش از حد مجاز است';
                // Do not descend further from an over-deep node.
                continue;
            }

            // Rule 3: uid format + uniqueness.
            if (!preg_match('/^[0-9a-f]{32}$/', $node->uid) || isset($seenUids[$node->uid])) {
                $errors[] = 'شناسه بلاک نامعتبر یا تکراری است';
            } else {
                $seenUids[$node->uid] = true;
            }

            // Rule 4: known type.
            $definition = BuilderBlockRegistry::get($node->type);
            if ($definition === null) {
                $errors[] = 'نوع بلاک نامعتبر است';
                $dirty = preg_replace('/[\x00-\x1F\x7F]/', '', substr($node->type, 0, 50));
                error_log('BuilderTreeValidator: unknown block type: ' . $dirty);
                // Still descend into children so nested errors are reported,
                // but skip type-dependent rules for this node.
                for ($i = count($node->children) - 1; $i >= 0; $i--) {
                    $stack[] = ['node' => $node->children[$i], 'parentType' => $node->type, 'depth' => $depth + 1, 'isRoot' => false];
                }
                continue;
            }

            // Rule 5: header/body/footer only at root; nothing else at root.
            $isStructuralRoot = in_array($node->type, ['header', 'body', 'footer'], true);
            if ($isRoot && !$isStructuralRoot) {
                $errors[] = 'بلاک ساختاری فقط میتواند در ریشه قرار بگیرد';
            } elseif (!$isRoot && $isStructuralRoot) {
                $errors[] = 'بلاک ساختاری فقط میتواند در ریشه قرار بگیرد';
            }

            // Rule 6: leaf types must have no children.
            if ($definition['allows_children'] === false && $node->children !== []) {
                $errors[] = 'این نوع بلاک نمیتواند فرزند داشته باشد';
            }

            // Rule 7: min/max children (0 = unlimited).
            $childCount = count($node->children);
            $min = $definition['min_children'];
            $max = $definition['max_children'];
            if ($childCount < $min || ($max !== 0 && $childCount > $max)) {
                $errors[] = 'تعداد فرزندهای بلاک مجاز نیست';
            }

            // Rule 10: per-node data size.
            $encodedData = json_encode($node->data, JSON_UNESCAPED_UNICODE);
            if ($encodedData === false || strlen($encodedData) > self::MAX_DATA_BYTES) {
                $errors[] = 'حجم دادهی بلاک بیش از حد مجاز است';
            }

            // Rule 11: allowed parent/child types ([] = no restriction).
            if ($parentType !== null) {
                $parentDef = BuilderBlockRegistry::get($parentType);
                if ($parentDef !== null && count($parentDef['allowed_child_types']) > 0
                    && !in_array($node->type, $parentDef['allowed_child_types'], true)) {
                    $errors[] = 'این نوع بلاک در این محل مجاز نیست';
                }
            }
            if (count($definition['allowed_parent_types']) > 0 && $parentType !== null
                && !in_array($parentType, $definition['allowed_parent_types'], true)) {
                $errors[] = 'این نوع بلاک در این محل مجاز نیست';
            } elseif (count($definition['allowed_parent_types']) > 0 && $parentType === null) {
                // A type that requires a specific parent placed at the root
                // (only reachable for non-structural types, since structural
                // root slots are checked by rule 5 first).
                if (!$isStructuralRoot) {
                    $errors[] = 'این نوع بلاک در این محل مجاز نیست';
                }
            }

            // Push children (skip descending when children were already
            // rejected by rule 6? No — still validate them individually).
            for ($i = $childCount - 1; $i >= 0; $i--) {
                $stack[] = ['node' => $node->children[$i], 'parentType' => $node->type, 'depth' => $depth + 1, 'isRoot' => false];
            }
        }

        // Rule 8: total node count.
        if ($nodeCount > self::MAX_BLOCKS) {
            $errors[] = 'تعداد بلاکها بیش از حد مجاز است';
        }

        return $errors;
    }
}
