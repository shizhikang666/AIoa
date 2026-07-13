<?php

declare(strict_types=1);

namespace app\service\user;

/**
 * Builds Java OA compatible tree payloads from flat rows.
 */
class TreeBuilder
{
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function build(array $rows, string $idKey = 'ID', string $parentKey = 'PARENT_ID', string $rootParent = '0'): array
    {
        $itemsById = [];
        $childrenByParent = [];

        foreach ($rows as $row) {
            $id = (string)($row[$idKey] ?? '');
            if ($id === '') {
                continue;
            }

            $parentId = (string)($row[$parentKey] ?? $rootParent);
            $row['children'] = [];
            $itemsById[$id] = $row;
            $childrenByParent[$parentId][] = $id;
        }

        $visited = [];
        $buildChildren = function (string $parentId) use (&$buildChildren, &$visited, $childrenByParent, $itemsById): array {
            $tree = [];

            foreach ($childrenByParent[$parentId] ?? [] as $id) {
                if (isset($visited[$id]) || !isset($itemsById[$id])) {
                    continue;
                }

                $visited[$id] = true;
                $item = $itemsById[$id];
                $item['children'] = $buildChildren($id);
                $tree[] = $item;
            }

            return $tree;
        };

        $tree = $buildChildren($rootParent);

        foreach ($itemsById as $id => $row) {
            if (!isset($visited[$id])) {
                $visited[$id] = true;
                $row['children'] = $buildChildren($id);
                $tree[] = $row;
            }
        }

        return $tree;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    public function toSelector(array $nodes): array
    {
        return array_map(function (array $node): array {
            $children = $node['children'] ?? [];

            return [
                'id' => $node['ID'] ?? null,
                'parentId' => $node['PARENT_ID'] ?? null,
                'value' => $node['ID'] ?? null,
                'name' => $node['NAME'] ?? null,
                'label' => $node['NAME'] ?? null,
                'title' => $node['NAME'] ?? null,
                'children' => is_array($children) ? $this->toSelector($children) : [],
            ];
        }, $nodes);
    }
}
