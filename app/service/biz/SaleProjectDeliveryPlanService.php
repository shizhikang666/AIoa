<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Stores delivery arrangements created with a sale-project deal.
 *
 * A plan is deliberately isolated from biz_sale_project_invoice: invoice rows,
 * delivery records and inventory changes continue to represent real shipments
 * only. The caller must invoke prepareShipment() and markShipped() inside the
 * same outer transaction that creates the real delivery invoice.
 */
class SaleProjectDeliveryPlanService
{
    public const TABLE = 'biz_sale_project_delivery_plan';
    public const STATUS_WAIT_DELIVER = 'WAIT_DELIVER';
    public const STATUS_SHIPPED = 'SHIPPED';

    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const REISSUE_CATEGORY = 'REISSUE_ORDER';
    private const MAX_PLAN_COUNT = 50;
    private const MAX_TOTAL_ITEM_COUNT = 500;

    public function tableExists(): bool
    {
        $rows = Db::query(
            "SELECT 1 AS FOUND FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . self::TABLE . "' LIMIT 1"
        );

        return $rows !== [];
    }

    /**
     * Validate draft plans against the submitted deal product list.
     *
     * @param array<int, array<string|int, mixed>> $deliveryPlanList
     * @param array<int, array<string|int, mixed>> $productList
     * @return array<int, array<string, mixed>>
     */
    public function normalizeAndValidate(array $deliveryPlanList, array $productList): array
    {
        if ($deliveryPlanList === []) {
            return [];
        }
        if (!array_is_list($deliveryPlanList)) {
            throw new RuntimeException('invalid deliveryPlanList', 400);
        }
        if (count($deliveryPlanList) > self::MAX_PLAN_COUNT) {
            throw new RuntimeException('too many delivery plans', 400);
        }

        $products = $this->normalizeProductList($productList);
        if ($products === []) {
            throw new RuntimeException('missing productList for delivery plans', 400);
        }

        $plans = [];
        $allocatedByProductId = [];
        $totalItemCount = 0;
        foreach ($deliveryPlanList as $index => $planInput) {
            if (!is_array($planInput)) {
                throw new RuntimeException("invalid deliveryPlanList.{$index}", 400);
            }

            $itemInputList = $this->inputValue(
                $planInput,
                ['productItemList', 'productList', 'projectProductItemList', 'itemList', 'items']
            );
            if (!is_array($itemInputList) || !array_is_list($itemInputList) || $itemInputList === []) {
                throw new RuntimeException("missing deliveryPlanList.{$index}.productItemList", 400);
            }
            $totalItemCount += count($itemInputList);
            if ($totalItemCount > self::MAX_TOTAL_ITEM_COUNT) {
                throw new RuntimeException('too many delivery plan items', 400);
            }

            $items = [];
            $seenProductIds = [];
            foreach ($itemInputList as $itemIndex => $itemInput) {
                if (!is_array($itemInput)) {
                    throw new RuntimeException("invalid deliveryPlanList.{$index}.productItemList.{$itemIndex}", 400);
                }

                $productId = $this->optionalIdentifierValue(
                    $this->inputValue($itemInput, ['productId', 'PRODUCT_ID', 'targetId', 'TARGET_ID'])
                );
                if ($productId === null) {
                    $candidate = $this->optionalIdentifierValue($this->inputValue($itemInput, ['id', 'ID']));
                    if ($candidate !== null && isset($products[$candidate])) {
                        $productId = $candidate;
                    }
                }
                if ($productId === null || !isset($products[$productId])) {
                    throw new RuntimeException("delivery plan product not found: {$index}.{$itemIndex}", 400);
                }
                if (isset($seenProductIds[$productId])) {
                    throw new RuntimeException("duplicate product in delivery plan: {$productId}", 400);
                }
                $seenProductIds[$productId] = true;

                $amount = $this->positiveIntegerString(
                    $this->inputValue($itemInput, ['amount', 'AMOUNT', 'number', 'NUMBER']),
                    "deliveryPlanList.{$index}.productItemList.{$itemIndex}.amount"
                );
                $allocatedByProductId[$productId] = ($allocatedByProductId[$productId] ?? 0) + (int)$amount;
                $items[] = [
                    'productId' => $productId,
                    'amount' => $amount,
                    'remark' => $this->optionalTextValue(
                        $this->inputValue($itemInput, ['remark', 'REMARK']),
                        "deliveryPlanList.{$index}.productItemList.{$itemIndex}.remark",
                        100
                    ) ?? '',
                ];
            }

            $plans[] = [
                'planNo' => $index + 1,
                'status' => self::STATUS_WAIT_DELIVER,
                'consignee' => $this->requiredTextValue(
                    $this->inputValue($planInput, ['consignee', 'CONSIGNEE']),
                    "deliveryPlanList.{$index}.consignee",
                    40
                ),
                'unit' => $this->requiredTextValue(
                    $this->inputValue($planInput, ['unit', 'UNIT']),
                    "deliveryPlanList.{$index}.unit",
                    100
                ),
                'phone' => $this->requiredTextValue(
                    $this->inputValue($planInput, ['phone', 'PHONE']),
                    "deliveryPlanList.{$index}.phone",
                    40
                ),
                'address' => $this->requiredTextValue(
                    $this->inputValue($planInput, ['address', 'ADDRESS']),
                    "deliveryPlanList.{$index}.address",
                    100
                ),
                'freightCategory' => $this->optionalTextValue(
                    $this->inputValue($planInput, ['freightCategory', 'FREIGHT_CATEGORY']),
                    "deliveryPlanList.{$index}.freightCategory",
                    20
                ),
                'freight' => $this->optionalNonNegativeMoney(
                    $this->inputValue($planInput, ['freight', 'FREIGHT']),
                    "deliveryPlanList.{$index}.freight"
                ),
                'logisticsCategory' => $this->optionalTextValue(
                    $this->inputValue($planInput, ['logisticsCategory', 'LOGISTICS_CATEGORY']),
                    "deliveryPlanList.{$index}.logisticsCategory",
                    20
                ) ?? '',
                'remark' => $this->optionalTextValue(
                    $this->inputValue($planInput, ['remark', 'REMARK']),
                    "deliveryPlanList.{$index}.remark",
                    4000
                ),
                'productItemList' => $items,
            ];
        }

        foreach ($products as $productId => $product) {
            $expected = (int)$product['number'];
            $allocated = $allocatedByProductId[$productId] ?? 0;
            if ($allocated !== $expected) {
                throw new RuntimeException(
                    "delivery plan quantity mismatch for product {$productId}: expected {$expected}, got {$allocated}",
                    400
                );
            }
        }

        return $plans;
    }

    /**
     * Persist plans after SaleProjectService has synchronized project items.
     * Existing identical plans are returned to make a retry idempotent.
     *
     * @param array<int, array<string|int, mixed>> $deliveryPlanList
     * @return array<int, array<string, mixed>>
     */
    public function createForProject(
        string $projectId,
        array $deliveryPlanList,
        string $tenantId,
        string $currentUserId
    ): array {
        $projectId = $this->requiredIdentifier($projectId, 'projectId');
        $tenantId = $this->tenantId($tenantId);
        $currentUserId = $this->auditUserId($currentUserId);
        if ($deliveryPlanList === []) {
            return [];
        }
        $this->requireTable();

        return Db::transaction(function () use ($projectId, $deliveryPlanList, $tenantId, $currentUserId): array {
            $projectQuery = Db::name('biz_sale_project')
                ->where('ID', $projectId)
                ->where('TENANT_ID', $tenantId);
            $this->whereNotDeleted($projectQuery, 'DELETE_FLAG');
            $project = $projectQuery->field('ID,TENANT_ID')->lock(true)->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }

            $productRows = $this->projectProductRows($projectId, $tenantId, true);
            if ($productRows === []) {
                throw new RuntimeException('sale project product item not found', 404);
            }
            $productList = array_map(static fn (array $row): array => [
                'id' => (string)$row['ID'],
                'productId' => (string)$row['PRODUCT_ID'],
                'number' => (string)$row['NUMBER'],
            ], $productRows);
            $normalized = $this->normalizeAndValidate($deliveryPlanList, $productList);

            $productRowsByProductId = [];
            foreach ($productRows as $row) {
                $productId = (string)$row['PRODUCT_ID'];
                if (isset($productRowsByProductId[$productId])) {
                    throw new RuntimeException('duplicate project product item productId', 409);
                }
                $productRowsByProductId[$productId] = $row;
            }

            $materialized = [];
            foreach ($normalized as $plan) {
                foreach ($plan['productItemList'] as &$item) {
                    $projectItem = $productRowsByProductId[(string)$item['productId']] ?? null;
                    if (!is_array($projectItem)) {
                        throw new RuntimeException('sale project product item not found', 404);
                    }
                    $item['projectProductItemId'] = (string)$projectItem['ID'];
                }
                unset($item);
                $materialized[] = $plan;
            }

            $existingRows = $this->planRowsForProject($projectId, $tenantId, true);
            if ($existingRows !== []) {
                $existingPlans = array_map(fn (array $row): array => $this->planRow($row), $existingRows);
                if ($this->planFingerprint($existingPlans) !== $this->planFingerprint($materialized)) {
                    throw new RuntimeException('delivery plans already exist for sale project', 409);
                }

                return $this->hydratePlanRows($existingRows, $tenantId);
            }

            $now = date('Y-m-d H:i:s');
            foreach ($materialized as $plan) {
                Db::name(self::TABLE)->insert([
                    'ID' => $this->newId(),
                    'PROJECT_ID' => $projectId,
                    'PLAN_NO' => (int)$plan['planNo'],
                    'STATUS' => self::STATUS_WAIT_DELIVER,
                    'CONSIGNEE' => $plan['consignee'],
                    'UNIT' => $plan['unit'],
                    'PHONE' => $plan['phone'],
                    'ADDRESS' => $plan['address'],
                    'FREIGHT_CATEGORY' => $plan['freightCategory'],
                    'FREIGHT' => $plan['freight'],
                    'LOGISTICS_CATEGORY' => $plan['logisticsCategory'],
                    'REMARK' => $plan['remark'],
                    'ITEM_JSON' => $this->encodeItems($plan['productItemList']),
                    'INVOICE_ID' => null,
                    'PROCESS_ID' => null,
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'CREATE_TIME' => $now,
                    'CREATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'UPDATE_TIME' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $tenantId,
                    'VERSION' => 0,
                ]);
            }

            return $this->hydratePlanRows($this->planRowsForProject($projectId, $tenantId, false), $tenantId);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByProject(string $projectId, string $tenantId): array
    {
        $projectId = $this->requiredIdentifier($projectId, 'projectId');
        $tenantId = $this->tenantId($tenantId);
        if (!$this->tableExists()) {
            return [];
        }

        return $this->hydratePlanRows($this->planRowsForProject($projectId, $tenantId, false), $tenantId);
    }

    /**
     * Lock and canonicalize a pending plan for the existing shipment workflow.
     * The caller should keep an outer database transaction open until the real
     * invoice is created and markShipped() succeeds.
     *
     * @param array<int, array<string|int, mixed>> $shipmentItems
     * @return array{plan: array<string, mixed>, projectProductItemList: array<int, array<string, mixed>>}
     */
    public function prepareShipment(
        string $planId,
        string $projectId,
        array $shipmentItems,
        string $tenantId
    ): array {
        $planId = $this->requiredIdentifier($planId, 'deliveryPlanId');
        $projectId = $this->requiredIdentifier($projectId, 'projectId');
        $tenantId = $this->tenantId($tenantId);
        $this->requireTable();

        $plan = $this->activePlan($planId, $projectId, $tenantId, true);
        if ((string)$plan['STATUS'] !== self::STATUS_WAIT_DELIVER) {
            throw new RuntimeException('delivery plan is not waiting for shipment', 409);
        }

        $plannedItems = $this->decodeItems((string)($plan['ITEM_JSON'] ?? ''));
        if ($plannedItems === []) {
            throw new RuntimeException('delivery plan has no items', 409);
        }
        if (!array_is_list($shipmentItems) || count($shipmentItems) !== count($plannedItems)) {
            throw new RuntimeException('shipment items do not match delivery plan', 400);
        }

        $plannedByItemId = [];
        $plannedByProductId = [];
        foreach ($plannedItems as $plannedItem) {
            $itemId = $this->requiredIdentifier(
                (string)($plannedItem['projectProductItemId'] ?? ''),
                'delivery plan projectProductItemId'
            );
            $productId = $this->requiredIdentifier((string)($plannedItem['productId'] ?? ''), 'delivery plan productId');
            if (isset($plannedByItemId[$itemId]) || isset($plannedByProductId[$productId])) {
                throw new RuntimeException('delivery plan contains duplicate items', 409);
            }
            $plannedByItemId[$itemId] = $plannedItem;
            $plannedByProductId[$productId] = $plannedItem;
        }

        $assignmentByItemId = [];
        $warehouseIds = [];
        foreach ($shipmentItems as $index => $shipmentItem) {
            if (!is_array($shipmentItem)) {
                throw new RuntimeException("invalid projectProductItemList.{$index}", 400);
            }
            $itemId = $this->optionalIdentifierValue(
                $this->inputValue($shipmentItem, ['projectProductItemId', 'PROJECT_PRODUCT_ITEM_ID', 'id', 'ID'])
            );
            $productId = $this->optionalIdentifierValue($this->inputValue($shipmentItem, ['productId', 'PRODUCT_ID']));
            $plannedItem = $itemId !== null ? ($plannedByItemId[$itemId] ?? null) : null;
            if (!is_array($plannedItem) && $productId !== null) {
                $plannedItem = $plannedByProductId[$productId] ?? null;
                $itemId = is_array($plannedItem) ? (string)$plannedItem['projectProductItemId'] : null;
            }
            if (!is_array($plannedItem) || $itemId === null || isset($assignmentByItemId[$itemId])) {
                throw new RuntimeException('shipment items do not match delivery plan', 400);
            }

            if ($productId !== null && $productId !== (string)$plannedItem['productId']) {
                throw new RuntimeException('shipment product does not match delivery plan', 400);
            }
            $submittedAmount = $this->inputValue($shipmentItem, ['amount', 'AMOUNT']);
            if ($submittedAmount !== null && $submittedAmount !== '') {
                $amount = $this->positiveIntegerString($submittedAmount, "projectProductItemList.{$index}.amount");
                if ($amount !== (string)$plannedItem['amount']) {
                    throw new RuntimeException('shipment amount does not match delivery plan', 400);
                }
            }

            $warehouseId = $this->requiredIdentifier(
                (string)($this->inputValue($shipmentItem, ['warehousesId', 'WAREHOUSES_ID', 'warehouseId']) ?? ''),
                "projectProductItemList.{$index}.warehousesId"
            );
            $warehouseIds[] = $warehouseId;
            $assignmentByItemId[$itemId] = $warehouseId;
        }
        if (count($assignmentByItemId) !== count($plannedByItemId)) {
            throw new RuntimeException('shipment items do not match delivery plan', 400);
        }
        $this->assertWarehouses(array_values(array_unique($warehouseIds)), $tenantId);

        $projectItemIds = array_keys($plannedByItemId);
        $itemQuery = Db::name('biz_sale_project_product_item')
            ->alias('i')
            ->leftJoin('biz_product product', 'product.ID = i.PRODUCT_ID')
            ->whereIn('i.ID', $projectItemIds)
            ->where('i.PROJECT_ID', $projectId)
            ->where('i.TENANT_ID', $tenantId)
            ->field(
                'i.ID,i.PROJECT_ID,i.PRODUCT_ID,i.NUMBER,i.DELIVERY,i.CATEGORY,i.TENANT_ID,'
                . 'product.PRODUCT_NAME AS PRODUCT_NAME,product.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,product.SPECS AS SPECS'
            );
        $this->whereNotDeleted($itemQuery, 'i.DELETE_FLAG');
        $projectItems = $itemQuery->lock(true)->select()->toArray();
        if (count($projectItems) !== count($projectItemIds)) {
            throw new RuntimeException('sale project product item not found', 404);
        }
        $projectItemsById = [];
        foreach ($projectItems as $projectItem) {
            $projectItemsById[(string)$projectItem['ID']] = $projectItem;
        }

        $canonicalItems = [];
        $productMeta = [];
        foreach ($plannedItems as $plannedItem) {
            $itemId = (string)$plannedItem['projectProductItemId'];
            $projectItem = $projectItemsById[$itemId] ?? null;
            if (!is_array($projectItem)
                || (string)$projectItem['PRODUCT_ID'] !== (string)$plannedItem['productId']
                || (string)($projectItem['CATEGORY'] ?? '') === self::REISSUE_CATEGORY) {
                throw new RuntimeException('delivery plan project item mismatch', 409);
            }
            $remaining = (float)$projectItem['NUMBER'] - (float)$projectItem['DELIVERY'];
            if ((float)$plannedItem['amount'] > $remaining + 0.000001) {
                throw new RuntimeException('delivery plan amount exceeds remaining project quantity', 409);
            }

            $canonicalItems[] = [
                'projectProductItemId' => $itemId,
                'productId' => (string)$plannedItem['productId'],
                'warehousesId' => $assignmentByItemId[$itemId],
                'amount' => (string)$plannedItem['amount'],
                'remark' => (string)($plannedItem['remark'] ?? ''),
            ];
            $productMeta[$itemId] = $projectItem;
        }

        $planResult = $this->planRow($plan, $productMeta);
        $planResult['productItemList'] = array_map(
            fn (array $item): array => $this->displayItem($item, $productMeta),
            $plannedItems
        );

        return [
            'plan' => $planResult,
            'projectProductItemList' => $canonicalItems,
        ];
    }

    /**
     * Mark a plan shipped after the real invoice and inventory side effects have
     * succeeded. Repeating the same linkage is idempotent.
     *
     * @return array<string, mixed>
     */
    public function markShipped(
        string $planId,
        string $projectId,
        string $invoiceId,
        string $processId,
        string $tenantId,
        string $currentUserId
    ): array {
        $planId = $this->requiredIdentifier($planId, 'deliveryPlanId');
        $projectId = $this->requiredIdentifier($projectId, 'projectId');
        $invoiceId = $this->requiredIdentifier($invoiceId, 'invoiceId');
        $processId = $this->requiredProcessId($processId);
        $tenantId = $this->tenantId($tenantId);
        $currentUserId = $this->auditUserId($currentUserId);
        $this->requireTable();

        return Db::transaction(function () use (
            $planId,
            $projectId,
            $invoiceId,
            $processId,
            $tenantId,
            $currentUserId
        ): array {
            $projectQuery = Db::name('biz_sale_project')
                ->where('ID', $projectId)
                ->where('TENANT_ID', $tenantId);
            $this->whereNotDeleted($projectQuery, 'DELETE_FLAG');
            $project = $projectQuery->field('ID')->lock(true)->find();
            if (!is_array($project) || $project === []) {
                throw new RuntimeException('sale project not found', 404);
            }

            $plan = $this->activePlan($planId, $projectId, $tenantId, true);
            $invoiceQuery = Db::name('biz_sale_project_invoice')
                ->where('ID', $invoiceId)
                ->where('PROJECT_ID', $projectId)
                ->where('TENANT_ID', $tenantId);
            $this->whereNotDeleted($invoiceQuery, 'DELETE_FLAG');
            $invoice = $invoiceQuery
                ->field('ID,PROJECT_ID,PROCESS_ID,FREIGHT_CATEGORY,FREIGHT,TENANT_ID')
                ->find();

            if ((string)$plan['STATUS'] === self::STATUS_SHIPPED) {
                if ((string)($plan['INVOICE_ID'] ?? '') !== $invoiceId
                    || (string)($plan['PROCESS_ID'] ?? '') !== $processId) {
                    throw new RuntimeException('delivery plan is already linked to another shipment', 409);
                }
                if (!is_array($invoice) || $invoice === [] || (string)$invoice['PROCESS_ID'] !== $processId) {
                    $this->syncProjectFreightProjection($projectId, $tenantId, $currentUserId);

                    return $this->planRow($this->activePlan($planId, $projectId, $tenantId, false));
                }

                $finalFreightCategory = $this->requiredTextValue(
                    $invoice['FREIGHT_CATEGORY'] ?? null,
                    'invoice.freightCategory',
                    20
                );
                $finalFreight = $this->nonNegativeMoney($invoice['FREIGHT'] ?? null, 'invoice.freight');
                $storedFreightCategory = $this->nullableString($plan['FREIGHT_CATEGORY'] ?? null);
                $storedFreight = $this->decimal($plan['FREIGHT'] ?? null);
                if ($storedFreightCategory !== $finalFreightCategory
                    || $storedFreight === null
                    || abs((float)$storedFreight - (float)$finalFreight) > 0.000001) {
                    Db::name(self::TABLE)
                        ->where('ID', $planId)
                        ->where('PROJECT_ID', $projectId)
                        ->where('TENANT_ID', $tenantId)
                        ->where('STATUS', self::STATUS_SHIPPED)
                        ->update([
                            'FREIGHT_CATEGORY' => $finalFreightCategory,
                            'FREIGHT' => $finalFreight,
                            'UPDATE_TIME' => date('Y-m-d H:i:s'),
                            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                            'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                        ]);
                }
                $this->syncProjectFreightProjection($projectId, $tenantId, $currentUserId);

                return $this->planRow($this->activePlan($planId, $projectId, $tenantId, false));
            }
            if ((string)$plan['STATUS'] !== self::STATUS_WAIT_DELIVER) {
                throw new RuntimeException('delivery plan is not waiting for shipment', 409);
            }
            if (!is_array($invoice) || $invoice === []) {
                throw new RuntimeException('sale project invoice not found', 404);
            }
            if ((string)$invoice['PROCESS_ID'] !== $processId) {
                throw new RuntimeException('delivery plan process does not match invoice', 409);
            }
            $finalFreightCategory = $this->requiredTextValue(
                $invoice['FREIGHT_CATEGORY'] ?? null,
                'invoice.freightCategory',
                20
            );
            $finalFreight = $this->nonNegativeMoney($invoice['FREIGHT'] ?? null, 'invoice.freight');

            $now = date('Y-m-d H:i:s');
            $updated = Db::name(self::TABLE)
                ->where('ID', $planId)
                ->where('PROJECT_ID', $projectId)
                ->where('TENANT_ID', $tenantId)
                ->where('STATUS', self::STATUS_WAIT_DELIVER)
                ->update([
                    'STATUS' => self::STATUS_SHIPPED,
                    'INVOICE_ID' => $invoiceId,
                    'PROCESS_ID' => $processId,
                    'FREIGHT_CATEGORY' => $finalFreightCategory,
                    'FREIGHT' => $finalFreight,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('delivery plan shipment state changed concurrently', 409);
            }
            $this->syncProjectFreightProjection($projectId, $tenantId, $currentUserId);

            return $this->planRow($this->activePlan($planId, $projectId, $tenantId, false));
        });
    }

    public function softDeletePendingByProject(
        string $projectId,
        string $tenantId,
        string $currentUserId
    ): int {
        $projectId = $this->requiredIdentifier($projectId, 'projectId');
        $tenantId = $this->tenantId($tenantId);
        $currentUserId = $this->auditUserId($currentUserId);
        if (!$this->tableExists()) {
            return 0;
        }

        return Db::transaction(function () use ($projectId, $tenantId, $currentUserId): int {
            $query = Db::name(self::TABLE)
                ->where('PROJECT_ID', $projectId)
                ->where('TENANT_ID', $tenantId)
                ->where('STATUS', self::STATUS_WAIT_DELIVER);
            $this->whereNotDeleted($query, 'DELETE_FLAG');
            $ids = array_map('strval', $query->lock(true)->column('ID'));
            if ($ids === []) {
                return 0;
            }

            return Db::name(self::TABLE)
                ->whereIn('ID', $ids)
                ->where('TENANT_ID', $tenantId)
                ->where('STATUS', self::STATUS_WAIT_DELIVER)
                ->update([
                    'DELETE_FLAG' => self::DELETED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);
        });
    }

    /**
     * @return array{pendingCount: int, pendingDeliveryPlanCount: int, shippedCount: int, totalCount: int, pendingItemCount: int, pendingQuantity: int|float, hasPending: bool, hasPendingDeliveryPlan: bool}
     */
    public function pendingSummary(string $projectId, string $tenantId): array
    {
        $projectId = $this->requiredIdentifier($projectId, 'projectId');
        $tenantId = $this->tenantId($tenantId);
        $empty = [
            'pendingCount' => 0,
            'pendingDeliveryPlanCount' => 0,
            'shippedCount' => 0,
            'totalCount' => 0,
            'pendingItemCount' => 0,
            'pendingQuantity' => 0,
            'hasPending' => false,
            'hasPendingDeliveryPlan' => false,
        ];
        if (!$this->tableExists()) {
            return $empty;
        }

        $rows = $this->planRowsForProject($projectId, $tenantId, false);
        $summary = $empty;
        foreach ($rows as $row) {
            $summary['totalCount']++;
            if ((string)$row['STATUS'] === self::STATUS_SHIPPED) {
                $summary['shippedCount']++;
                continue;
            }
            if ((string)$row['STATUS'] !== self::STATUS_WAIT_DELIVER) {
                continue;
            }
            $summary['pendingCount']++;
            $items = $this->decodeItems((string)($row['ITEM_JSON'] ?? ''));
            $summary['pendingItemCount'] += count($items);
            foreach ($items as $item) {
                $summary['pendingQuantity'] += (float)($item['amount'] ?? 0);
            }
        }
        $summary['pendingQuantity'] = $this->normalizedNumber((float)$summary['pendingQuantity']);
        $summary['hasPending'] = $summary['pendingCount'] > 0;
        $summary['pendingDeliveryPlanCount'] = $summary['pendingCount'];
        $summary['hasPendingDeliveryPlan'] = $summary['hasPending'];

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     * @return array<string, array{number: string}>
     */
    private function normalizeProductList(array $productList): array
    {
        if (!array_is_list($productList)) {
            throw new RuntimeException('invalid productList', 400);
        }

        $products = [];
        foreach ($productList as $index => $product) {
            if (!is_array($product)) {
                throw new RuntimeException("invalid productList.{$index}", 400);
            }
            $productId = $this->optionalIdentifierValue(
                $this->inputValue($product, ['productId', 'PRODUCT_ID', 'targetId', 'TARGET_ID'])
            );
            if ($productId === null) {
                $productId = $this->optionalIdentifierValue($this->inputValue($product, ['id', 'ID']));
            }
            if ($productId === null) {
                throw new RuntimeException("missing productList.{$index}.productId", 400);
            }
            if (isset($products[$productId])) {
                throw new RuntimeException("duplicate productList productId: {$productId}", 400);
            }
            $products[$productId] = [
                'number' => $this->positiveIntegerString(
                    $this->inputValue($product, ['number', 'NUMBER']),
                    "productList.{$index}.number"
                ),
            ];
        }

        return $products;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectProductRows(string $projectId, string $tenantId, bool $lock): array
    {
        $query = Db::name('biz_sale_project_product_item')
            ->where('PROJECT_ID', $projectId)
            ->where('TENANT_ID', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('CATEGORY')->whereOr('CATEGORY', '<>', self::REISSUE_CATEGORY);
            })
            ->field('ID,PROJECT_ID,PRODUCT_ID,NUMBER,DELIVERY,CATEGORY,TENANT_ID');
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($lock) {
            $query->lock(true);
        }

        return $query->order('ID', 'asc')->select()->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function planRowsForProject(string $projectId, string $tenantId, bool $lock): array
    {
        $query = Db::name(self::TABLE)
            ->where('PROJECT_ID', $projectId)
            ->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        if ($lock) {
            $query->lock(true);
        }

        return $query->order('PLAN_NO', 'asc')->order('ID', 'asc')->select()->toArray();
    }

    private function syncProjectFreightProjection(
        string $projectId,
        string $tenantId,
        string $currentUserId
    ): void {
        $planRows = $this->planRowsForProject($projectId, $tenantId, false);
        if ($planRows === []) {
            return;
        }

        $totalCents = 0;
        $hasFreight = false;
        foreach ($planRows as $planRow) {
            $freightValue = $planRow['FREIGHT'] ?? null;
            if ($freightValue === null || trim((string)$freightValue) === '') {
                continue;
            }
            $normalized = $this->nonNegativeMoney($freightValue, 'delivery plan freight');
            [$whole, $fraction] = explode('.', $normalized, 2);
            $totalCents += ((int)$whole * 100) + (int)$fraction;
            $hasFreight = true;
        }
        if ($totalCents > 999999999999999) {
            throw new RuntimeException('delivery plan freight total exceeds sale project limit', 400);
        }

        $projectFreight = null;
        if ($hasFreight) {
            $projectFreight = intdiv($totalCents, 100)
                . '.'
                . str_pad((string)($totalCents % 100), 2, '0', STR_PAD_LEFT);
        }

        $query = Db::name('biz_sale_project')
            ->where('ID', $projectId)
            ->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $updated = $query->update([
            'FREIGHT' => $projectFreight,
            'FREIGHT_CATEGORY' => $this->nullableString($planRows[0]['FREIGHT_CATEGORY'] ?? null),
            'UPDATE_TIME' => date('Y-m-d H:i:s'),
            'UPDATE_USER' => $currentUserId !== '' ? $currentUserId : null,
            'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
        ]);
        if ($updated !== 1) {
            throw new RuntimeException('sale project freight projection update failed', 409);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function activePlan(
        string $planId,
        string $projectId,
        string $tenantId,
        bool $lock
    ): array {
        $query = Db::name(self::TABLE)
            ->where('ID', $planId)
            ->where('PROJECT_ID', $projectId)
            ->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $row = $lock ? $query->lock(true)->find() : $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('delivery plan not found', 404);
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydratePlanRows(array $rows, string $tenantId): array
    {
        if ($rows === []) {
            return [];
        }

        $itemIds = [];
        foreach ($rows as $row) {
            foreach ($this->decodeItems((string)($row['ITEM_JSON'] ?? '')) as $item) {
                $itemId = trim((string)($item['projectProductItemId'] ?? ''));
                if ($itemId !== '') {
                    $itemIds[] = $itemId;
                }
            }
        }
        $itemIds = array_values(array_unique($itemIds));

        $productMeta = [];
        if ($itemIds !== []) {
            $metaRows = Db::name('biz_sale_project_product_item')
                ->alias('i')
                ->leftJoin('biz_product product', 'product.ID = i.PRODUCT_ID')
                ->whereIn('i.ID', $itemIds)
                ->where('i.TENANT_ID', $tenantId)
                ->field(
                    'i.ID,i.PROJECT_ID,i.PRODUCT_ID,i.NUMBER,i.DELIVERY,i.CATEGORY,'
                    . 'product.PRODUCT_NAME AS PRODUCT_NAME,product.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,product.SPECS AS SPECS'
                )
                ->select()
                ->toArray();
            foreach ($metaRows as $metaRow) {
                $productMeta[(string)$metaRow['ID']] = $metaRow;
            }
        }

        return array_map(fn (array $row): array => $this->planRow($row, $productMeta), $rows);
    }

    /**
     * @param array<string, array<string, mixed>> $productMeta
     * @return array<string, mixed>
     */
    private function planRow(array $row, array $productMeta = []): array
    {
        $items = array_map(
            fn (array $item): array => $this->displayItem($item, $productMeta),
            $this->decodeItems((string)($row['ITEM_JSON'] ?? ''))
        );
        $totalQuantity = 0.0;
        foreach ($items as $item) {
            $totalQuantity += (float)($item['amount'] ?? 0);
        }

        return [
            'id' => (string)($row['ID'] ?? ''),
            'projectId' => (string)($row['PROJECT_ID'] ?? ''),
            'planNo' => (int)($row['PLAN_NO'] ?? 0),
            'status' => (string)($row['STATUS'] ?? self::STATUS_WAIT_DELIVER),
            'invoiceId' => $this->nullableString($row['INVOICE_ID'] ?? null),
            'processId' => $this->nullableString($row['PROCESS_ID'] ?? null),
            'consignee' => (string)($row['CONSIGNEE'] ?? ''),
            'unit' => (string)($row['UNIT'] ?? ''),
            'phone' => (string)($row['PHONE'] ?? ''),
            'address' => (string)($row['ADDRESS'] ?? ''),
            'freightCategory' => $this->nullableString($row['FREIGHT_CATEGORY'] ?? null),
            'freight' => $this->decimal($row['FREIGHT'] ?? null),
            'logisticsCategory' => (string)($row['LOGISTICS_CATEGORY'] ?? ''),
            'remark' => $row['REMARK'] ?? null,
            'productItemList' => $items,
            'productList' => $items,
            'itemCount' => count($items),
            'totalQuantity' => $this->normalizedNumber($totalQuantity),
            'deleteFlag' => $row['DELETE_FLAG'] ?? null,
            'createTime' => $row['CREATE_TIME'] ?? null,
            'createUser' => $row['CREATE_USER'] ?? null,
            'updateTime' => $row['UPDATE_TIME'] ?? null,
            'updateUser' => $row['UPDATE_USER'] ?? null,
            'tenantId' => (string)($row['TENANT_ID'] ?? ''),
            'version' => (int)($row['VERSION'] ?? 0),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $productMeta
     * @return array<string, mixed>
     */
    private function displayItem(array $item, array $productMeta): array
    {
        $itemId = (string)($item['projectProductItemId'] ?? '');
        $meta = $productMeta[$itemId] ?? [];

        return [
            'projectProductItemId' => $itemId,
            'productId' => (string)($item['productId'] ?? ($meta['PRODUCT_ID'] ?? '')),
            'productName' => $meta['PRODUCT_NAME'] ?? null,
            'productCategory' => $meta['PRODUCT_CATEGORY'] ?? null,
            'specs' => $meta['SPECS'] ?? null,
            'amount' => $this->normalizedNumber((float)($item['amount'] ?? 0)),
            'remark' => (string)($item['remark'] ?? ''),
            'number' => isset($meta['NUMBER']) ? $this->normalizedNumber((float)$meta['NUMBER']) : null,
            'delivery' => isset($meta['DELIVERY']) ? $this->normalizedNumber((float)$meta['DELIVERY']) : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $plans
     */
    private function planFingerprint(array $plans): string
    {
        $canonical = [];
        foreach ($plans as $plan) {
            $items = [];
            foreach (($plan['productItemList'] ?? []) as $item) {
                $items[] = [
                    'productId' => (string)($item['productId'] ?? ''),
                    'amount' => (string)($item['amount'] ?? ''),
                    'remark' => (string)($item['remark'] ?? ''),
                ];
            }
            $canonical[] = [
                'planNo' => (int)($plan['planNo'] ?? 0),
                'consignee' => (string)($plan['consignee'] ?? ''),
                'unit' => (string)($plan['unit'] ?? ''),
                'phone' => (string)($plan['phone'] ?? ''),
                'address' => (string)($plan['address'] ?? ''),
                'freightCategory' => $this->nullableString($plan['freightCategory'] ?? null),
                'freight' => ($plan['freight'] ?? null) === null || $plan['freight'] === ''
                    ? null
                    : number_format((float)$plan['freight'], 2, '.', ''),
                'logisticsCategory' => (string)($plan['logisticsCategory'] ?? ''),
                'remark' => $plan['remark'] ?? null,
                'productItemList' => $items,
            ];
        }

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function encodeItems(array $items): string
    {
        return json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeItems(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }
        try {
            $items = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('invalid delivery plan item json', 500, $exception);
        }
        if (!is_array($items) || !array_is_list($items)) {
            throw new RuntimeException('invalid delivery plan item json', 500);
        }

        return $items;
    }

    /**
     * @param array<int, string> $warehouseIds
     */
    private function assertWarehouses(array $warehouseIds, string $tenantId): void
    {
        if ($warehouseIds === []) {
            throw new RuntimeException('missing shipment warehouse', 400);
        }
        $query = Db::name('warehouses')
            ->whereIn('ID', $warehouseIds)
            ->where('TENANT_ID', $tenantId);
        $this->whereNotDeleted($query, 'DELETE_FLAG');
        $found = array_map('strval', $query->column('ID'));
        sort($warehouseIds);
        sort($found);
        if ($warehouseIds !== $found) {
            throw new RuntimeException('warehouse not found', 404);
        }
    }

    private function requireTable(): void
    {
        if (!$this->tableExists()) {
            throw new RuntimeException('sale project delivery plan table is not installed', 503);
        }
    }

    private function inputValue(array $input, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return $input[$key];
            }
        }

        return null;
    }

    private function requiredIdentifier(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 20) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return $value;
    }

    private function optionalIdentifierValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value)) {
            return null;
        }
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 20) {
            return null;
        }

        return $value;
    }

    private function requiredProcessId(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 80) {
            throw new RuntimeException('invalid processId', 400);
        }

        return $value;
    }

    private function tenantId(string $tenantId): string
    {
        $tenantId = trim($tenantId);
        if ($tenantId === '') {
            return '1';
        }
        if (strlen($tenantId) > 20) {
            throw new RuntimeException('invalid tenantId', 400);
        }

        return $tenantId;
    }

    private function auditUserId(string $userId): string
    {
        $userId = trim($userId);
        if (strlen($userId) > 20) {
            throw new RuntimeException('invalid currentUserId', 400);
        }

        return $userId;
    }

    private function requiredTextValue(mixed $value, string $field, int $maxLength): string
    {
        $value = $this->optionalTextValue($value, $field, $maxLength);
        if ($value === null || $value === '') {
            throw new RuntimeException("missing {$field}", 400);
        }

        return $value;
    }

    private function optionalTextValue(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > $maxLength) {
            throw new RuntimeException("{$field} is too long", 400);
        }

        return $value;
    }

    private function positiveIntegerString(mixed $value, string $field): string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("missing {$field}", 400);
        }
        $raw = trim((string)$value);
        if (preg_match('/^\d+(?:\.0+)?$/', $raw) !== 1) {
            throw new RuntimeException("invalid {$field}", 400);
        }
        $whole = ltrim(explode('.', $raw, 2)[0], '0');
        $whole = $whole === '' ? '0' : $whole;
        if ($whole === '0' || strlen($whole) > 15) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return $whole;
    }

    private function nonNegativeMoney(mixed $value, string $field): string
    {
        if ($value === null || $value === '' || is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("missing {$field}", 400);
        }
        $raw = trim((string)$value);
        if (preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $raw) !== 1) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return number_format((float)$raw, 2, '.', '');
    }

    private function optionalNonNegativeMoney(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value) || is_bool($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }
        if (trim((string)$value) === '') {
            return null;
        }

        return $this->nonNegativeMoney($value, $field);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string)$value);

        return $value !== '' ? $value : null;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalizedNumber((float)$value);
    }

    private function normalizedNumber(float $value): int|float
    {
        return abs($value - round($value)) < 0.000001 ? (int)round($value) : $value;
    }

    private function whereNotDeleted($query, string $column): void
    {
        $query->where(function ($query) use ($column): void {
            $query->whereNull($column)->whereOr($column, '=', self::NOT_DELETE);
        });
    }

    private function newId(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
            if ((int)Db::name(self::TABLE)->where('ID', $id)->count() === 0) {
                return $id;
            }
        }

        throw new RuntimeException('failed to allocate delivery plan id', 500);
    }
}
