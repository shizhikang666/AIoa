<?php

declare(strict_types=1);

namespace app\service\biz;

use RuntimeException;
use think\facade\Db;

/**
 * Read-only purchase-order queries compatible with Java BizPurchaseOrderController.
 */
class PurchaseOrderService
{
    private const NOT_DELETE = 'NOT_DELETE';
    private const DELETED = 'DELETED';
    private const SETTLEMENT_NOT_COMPLETED = 'NOT_COMPLETED';
    private const SETTLEMENT_COMPLETED = 'COMPLETED';
    private const SETTLEMENT_CANCELED = 'Canceled';
    private const STORAGE_NOT_IN_WAREHOUSE = 'NOT_IN_WAREHOUSE';
    private const STORAGE_IN_WAREHOUSE = 'IN_WAREHOUSE';
    private const GOODS_EXPENDITURE = 'GOODS_EXPENDITURE';
    private const PROCESS_SYS = 'Process_sys';
    private const PROCESS_PROCURE = 'Process_procure';
    private const PROCESS_PROCURE_IN_WAREHOUSE = 'Process_procure_in_warehouse';
    private const DELIVERY_CATEGORY_IN = 'IN';
    private const PRODUCT_CATEGORY_SINGLE = 'SINGLE_PRODUCT';
    private const ORDER_FIELDS = <<<SQL
o.ID AS ID,
o.TITLE AS TITLE,
o.SETTLEMENT_STATUS AS SETTLEMENT_STATUS,
o.STORAGE_STATUS AS STORAGE_STATUS,
o.SUPPLIER_ID AS SUPPLIER_ID,
o.INSTANCE_ID AS INSTANCE_ID,
o.DESIRE_PURCHASE_DATE AS DESIRE_PURCHASE_DATE,
o.AMOUNT AS AMOUNT,
o.REMARK AS REMARK,
o.EXT_JSON AS EXT_JSON,
o.DELETE_FLAG AS DELETE_FLAG,
o.CREATE_TIME AS CREATE_TIME,
o.CREATE_USER AS CREATE_USER,
o.UPDATE_TIME AS UPDATE_TIME,
o.UPDATE_USER AS UPDATE_USER,
o.TENANT_ID AS TENANT_ID,
o.VERSION AS VERSION,
o.ORG AS ORG,
org.NAME AS ORG_NAME
SQL;
    private const ITEM_FIELDS = <<<SQL
i.ID AS ID,
i.PURCHASE_ORDER_ID AS PURCHASE_ORDER_ID,
i.STORAGE_STATUS AS STORAGE_STATUS,
i.PRODUCT_ID AS PRODUCT_ID,
i.AMOUNT AS AMOUNT,
i.NUMBER AS NUMBER,
i.UNIT_AMOUNT AS UNIT_AMOUNT,
i.DISCOUNT_RATE AS DISCOUNT_RATE,
i.REMARK AS REMARK,
i.EXT_JSON AS EXT_JSON,
i.DELETE_FLAG AS DELETE_FLAG,
i.CREATE_TIME AS CREATE_TIME,
i.CREATE_USER AS CREATE_USER,
i.UPDATE_TIME AS UPDATE_TIME,
i.UPDATE_USER AS UPDATE_USER,
i.TENANT_ID AS TENANT_ID,
i.VERSION AS VERSION,
i.FREIGHT_SHARE_AMOUNT AS FREIGHT_SHARE_AMOUNT,
i.UNIT_COST_WITH_FREIGHT AS UNIT_COST_WITH_FREIGHT,
p.PRODUCT_NAME AS PRODUCT_NAME,
p.PRODUCT_CATEGORY AS PRODUCT_CATEGORY,
p.CATEGORY AS CATEGORY,
p.SPECS AS SPECS,
p.PURCHASE_PRICE AS PURCHASE_PRICE,
p.SALE_PRICE AS SALE_PRICE,
p.MIN_PRICE AS MIN_PRICE
SQL;
    private const SORT_FIELD_MAP = [
        'id' => 'o.ID',
        'title' => 'o.TITLE',
        'settlementStatus' => 'o.SETTLEMENT_STATUS',
        'storageStatus' => 'o.STORAGE_STATUS',
        'supplierId' => 'o.SUPPLIER_ID',
        'instanceId' => 'o.INSTANCE_ID',
        'desirePurchaseDate' => 'o.DESIRE_PURCHASE_DATE',
        'amount' => 'o.AMOUNT',
        'createTime' => 'o.CREATE_TIME',
        'updateTime' => 'o.UPDATE_TIME',
        'tenantId' => 'o.TENANT_ID',
        'version' => 'o.VERSION',
        'org' => 'o.ORG',
        'orgName' => 'org.NAME',
    ];

    public function page(array $filters = [], array $payload = []): array
    {
        [$page, $limit] = $this->pagination($filters);
        $total = $this->orderQuery($filters, $payload)->count();
        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'records' => $this->orderRows($rows),
            'total' => $total,
            'page' => $page,
            'current' => $page,
            'limit' => $limit,
            'size' => $limit,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->select()
            ->toArray();

        return $this->orderRows($rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function detailList(array $filters = [], array $payload = []): array
    {
        $rows = $this->applySort($this->orderQuery($filters, $payload), $filters)
            ->field(self::ORDER_FIELDS)
            ->select()
            ->toArray();
        $orders = $this->orderRows($rows);
        $itemsByOrderId = $this->itemsByOrderIds(array_column($orders, 'id'), $payload);

        foreach ($orders as &$order) {
            $order['orderItems'] = $itemsByOrderId[(string)$order['id']] ?? [];
        }
        unset($order);

        return $orders;
    }

    public function detail(string $id, array $payload = []): array
    {
        $row = $this->orderQuery(['id' => $id], $payload)
            ->field(self::ORDER_FIELDS)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('purchase order not found', 404);
        }

        return [
            'bizPurchaseOrder' => $this->orderRows([$row])[0],
            'bizPurchaseOrderItemList' => $this->itemRowsByOrderId($id, $payload),
            'bizExpenditureRecordList' => $this->expenditureRows($id, $payload),
        ];
    }

    public function add(array $input, array $payload = []): array
    {
        $title = $this->workflowRequiredString($input['title'] ?? null, 'title', 40);
        $supplier = $this->workflowObject($input['supplier'] ?? null, 'supplier');
        $supplierId = $this->workflowOptionalString($input['supplierId'] ?? $supplier['id'] ?? $supplier['ID'] ?? null, 20) ?? '';
        $processInstanceId = $this->workflowOptionalString($input['instanceId'] ?? null, 40) ?? self::PROCESS_SYS;
        $desirePurchaseDate = $this->workflowDate($input['desirePurchaseDate'] ?? null, 'desirePurchaseDate');
        $productList = $this->workflowPurchaseProductList($input['productList'] ?? null);
        $productIds = array_values(array_unique(array_map(static fn (array $item): string => $item['productId'], $productList)));
        if (count($productIds) !== count($productList)) {
            throw new RuntimeException('duplicate productId', 400);
        }
        $amount = array_key_exists('amount', $input)
            ? $this->nonNegativeDecimal($input['amount'], 'amount')
            : $this->productAmountTotal($productList);
        $remark = $this->workflowOptionalString($input['remark'] ?? null, 65535);
        $org = $this->workflowOptionalString($input['org'] ?? null, 20) ?? $this->defaultOrgId($payload);
        $tenantId = $this->tenantId($input, $payload);

        return Db::transaction(function () use ($title, $supplier, $supplierId, $processInstanceId, $desirePurchaseDate, $amount, $remark, $org, $productList, $productIds, $tenantId, $payload): array {
            $this->assertCreateOrgWritable($org, $payload, 'add purchase order');
            $this->assertProductsExist($productIds, $tenantId);
            if ($supplierId !== '') {
                $this->assertSupplierWritable($this->activeSupplier($supplierId, $tenantId), $payload, 'use supplier');
            }

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $orderId = $this->newId();

            Db::name('biz_purchase_order')->insert([
                'ID' => $orderId,
                'TITLE' => $title,
                'SETTLEMENT_STATUS' => self::SETTLEMENT_NOT_COMPLETED,
                'STORAGE_STATUS' => self::STORAGE_NOT_IN_WAREHOUSE,
                'SUPPLIER_ID' => $supplierId,
                'INSTANCE_ID' => $processInstanceId,
                'DESIRE_PURCHASE_DATE' => $desirePurchaseDate,
                'AMOUNT' => $amount,
                'REMARK' => $remark,
                'EXT_JSON' => json_encode(['supplier' => $supplier], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'ORG' => $org,
            ]);

            $itemRows = [];
            foreach ($productList as $item) {
                $itemRows[] = [
                    'ID' => $this->newId(),
                    'PURCHASE_ORDER_ID' => $orderId,
                    'STORAGE_STATUS' => self::STORAGE_NOT_IN_WAREHOUSE,
                    'PRODUCT_ID' => $item['productId'],
                    'AMOUNT' => $item['amount'],
                    'NUMBER' => $item['number'],
                    'UNIT_AMOUNT' => $item['unitAmount'],
                    'DISCOUNT_RATE' => $item['discountRate'],
                    'REMARK' => $item['remark'],
                    'EXT_JSON' => null,
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'CREATE_TIME' => $now,
                    'CREATE_USER' => $userId !== '' ? $userId : null,
                    'UPDATE_TIME' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $tenantId,
                    'VERSION' => 0,
                    'FREIGHT_SHARE_AMOUNT' => '0',
                    'UNIT_COST_WITH_FREIGHT' => '0',
                ];
            }
            Db::name('biz_purchase_order_item')->insertAll($itemRows);

            return [
                'id' => $orderId,
                'instanceId' => $processInstanceId,
                'settlementStatus' => self::SETTLEMENT_NOT_COMPLETED,
                'storageStatus' => self::STORAGE_NOT_IN_WAREHOUSE,
                'amount' => $this->decimal($amount),
                'supplierId' => $supplierId,
                'itemCount' => count($itemRows),
                'count' => 1,
            ];
        });
    }

    /**
     * @param array<int, mixed> $ids
     */
    public function delete(array $ids, array $payload = []): array
    {
        $idList = $this->stringList($ids);
        if ($idList === []) {
            throw new RuntimeException('missing idList', 400);
        }

        return Db::transaction(function () use ($idList, $payload): array {
            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $deletedItems = 0;

            foreach ($idList as $id) {
                $order = $this->assertOrderWritable($id, $payload, 'delete this purchase order');
                $tenantId = trim((string)($order['TENANT_ID'] ?? ''));
                $settlementStatus = trim((string)($order['SETTLEMENT_STATUS'] ?? ''));
                $storageStatus = trim((string)($order['STORAGE_STATUS'] ?? ''));

                if ($settlementStatus === self::SETTLEMENT_COMPLETED) {
                    throw new RuntimeException('settled purchase order cannot be deleted', 400);
                }
                if ($storageStatus === self::STORAGE_IN_WAREHOUSE) {
                    throw new RuntimeException('warehoused purchase order cannot be deleted', 400);
                }
                if ($this->goodsExpenditureAmount($id, $tenantId) > 0) {
                    throw new RuntimeException('purchase order has expenditure records', 409);
                }
                if ($this->activeDeliveryCountForOrder($id, $tenantId) > 0) {
                    throw new RuntimeException('purchase order has delivery records', 409);
                }

                $items = $this->activeItemsByOrderForUpdate($id, $tenantId);
                foreach ($items as $item) {
                    if (trim((string)($item['STORAGE_STATUS'] ?? '')) === self::STORAGE_IN_WAREHOUSE) {
                        throw new RuntimeException('warehoused purchase order item cannot be deleted', 400);
                    }
                }

                $deletedItems += Db::name('biz_purchase_order_item')
                    ->where('PURCHASE_ORDER_ID', $id)
                    ->where(function ($query): void {
                        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                    })
                    ->update([
                        'DELETE_FLAG' => self::DELETED,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $userId !== '' ? $userId : null,
                        'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                    ]);

                Db::name('biz_purchase_order')
                    ->where('ID', $id)
                    ->update([
                        'DELETE_FLAG' => self::DELETED,
                        'UPDATE_TIME' => $now,
                        'UPDATE_USER' => $userId !== '' ? $userId : null,
                        'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                    ]);
            }

            return [
                'ids' => $idList,
                'count' => count($idList),
                'deletedItems' => $deletedItems,
            ];
        });
    }

    public function cancel(array $input, array $payload = []): array
    {
        $id = $this->requiredInput($input, 'id');

        return Db::transaction(function () use ($id, $payload): array {
            $order = $this->assertOrderWritable($id, $payload, 'cancel this purchase order');
            $settlementStatus = trim((string)($order['SETTLEMENT_STATUS'] ?? ''));
            $storageStatus = trim((string)($order['STORAGE_STATUS'] ?? ''));

            if ($settlementStatus === self::SETTLEMENT_COMPLETED) {
                throw new RuntimeException('采购单已结算不能修改状态', 400);
            }
            if ($storageStatus === self::STORAGE_IN_WAREHOUSE) {
                throw new RuntimeException('采购单已入库不能修改状态', 400);
            }

            $userId = $this->currentUserId($payload);
            $updated = Db::name('biz_purchase_order')
                ->where('ID', $id)
                ->update([
                    'SETTLEMENT_STATUS' => self::SETTLEMENT_CANCELED,
                    'UPDATE_TIME' => date('Y-m-d H:i:s'),
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return [
                'id' => $id,
                'settlementStatus' => self::SETTLEMENT_CANCELED,
                'previousSettlementStatus' => $settlementStatus,
                'storageStatus' => $storageStatus,
                'count' => $updated,
            ];
        });
    }

    public function edit(array $input, array $payload = []): array
    {
        return $this->editPurchaseOrder($input, $payload, false, 'edit this purchase order');
    }

    public function auditEdit(array $input, array $payload = []): array
    {
        return $this->editPurchaseOrder($input, $payload, true, 'audit edit this purchase order');
    }

    public function warehouseAdd(array $input, array $payload = []): array
    {
        $warehouseId = $this->requiredInput($input, 'warehousesId');

        return Db::transaction(function () use ($warehouseId, $payload): array {
            $orders = $this->eligibleWarehouseOrdersForUpdate($payload);
            if ($orders === []) {
                return [
                    'count' => 0,
                    'orderIds' => [],
                    'deliveryIds' => [],
                    'inventoryIds' => [],
                    'updatedItems' => 0,
                ];
            }

            $results = [];
            foreach ($orders as $order) {
                $results[] = $this->stockInLockedOrder($order, $warehouseId, null, $payload);
            }

            $deliveryIds = [];
            $inventoryIds = [];
            $updatedItems = 0;
            foreach ($results as $result) {
                $deliveryIds = array_merge($deliveryIds, $result['deliveryIds']);
                $inventoryIds = array_merge($inventoryIds, $result['inventoryIds']);
                $updatedItems += (int)$result['updatedItems'];
            }

            return [
                'count' => count($results),
                'orderIds' => array_column($results, 'id'),
                'deliveryIds' => $deliveryIds,
                'inventoryIds' => array_values(array_unique($inventoryIds)),
                'updatedItems' => $updatedItems,
            ];
        });
    }

    public function warehouseOneAdd(array $input, array $payload = []): array
    {
        $orderId = $this->requiredInput($input, 'orderId');
        $warehouseId = $this->requiredInput($input, 'warehousesId');
        $remark = array_key_exists('remark', $input) ? trim((string)$input['remark']) : null;

        return Db::transaction(function () use ($orderId, $warehouseId, $remark, $payload): array {
            $order = $this->assertOrderWritable($orderId, $payload, 'warehouse this purchase order');
            return $this->stockInLockedOrder($order, $warehouseId, $remark, $payload);
        });
    }

    public function warehouseOneFromWorkflow(
        array $input,
        string $processInstanceId,
        string $tenantId,
        string $operatorUserId
    ): array {
        $orderId = $this->requiredInput($input, 'orderId');
        $warehouseId = $this->requiredInput($input, 'warehousesId');
        $remark = array_key_exists('remark', $input) ? trim((string)$input['remark']) : null;
        $payload = [
            'tenant_id' => $tenantId,
            'user_id' => $operatorUserId,
        ];

        $order = $this->activeOrder($orderId, $payload);

        return $this->stockInLockedOrder(
            $order,
            $warehouseId,
            $remark,
            $payload,
            $processInstanceId,
            self::PROCESS_PROCURE_IN_WAREHOUSE,
            $operatorUserId
        );
    }

    public function purchaseOrderFromWorkflow(
        array $input,
        string $processInstanceId,
        string $tenantId,
        string $operatorUserId
    ): array {
        return Db::transaction(function () use ($input, $processInstanceId, $tenantId, $operatorUserId): array {
            $existing = Db::name('biz_purchase_order')
                ->where('INSTANCE_ID', $processInstanceId)
                ->where(function ($query): void {
                    $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                })
                ->find();
            if (is_array($existing) && $existing !== []) {
                $orderId = (string)$existing['ID'];
                $itemCount = Db::name('biz_purchase_order_item')
                    ->where('PURCHASE_ORDER_ID', $orderId)
                    ->where(function ($query): void {
                        $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
                    })
                    ->count();

                return [
                    'id' => $orderId,
                    'instanceId' => $processInstanceId,
                    'settlementStatus' => (string)($existing['SETTLEMENT_STATUS'] ?? self::SETTLEMENT_NOT_COMPLETED),
                    'storageStatus' => (string)($existing['STORAGE_STATUS'] ?? self::STORAGE_NOT_IN_WAREHOUSE),
                    'amount' => $this->decimal($existing['AMOUNT'] ?? null),
                    'itemCount' => (int)$itemCount,
                    'existing' => true,
                ];
            }

            $title = $this->workflowRequiredString($input['title'] ?? null, 'title', 40);
            $supplier = $this->workflowObject($input['supplier'] ?? null, 'supplier');
            $supplierId = $this->workflowOptionalString($supplier['id'] ?? $supplier['ID'] ?? null, 20) ?? '';
            $desirePurchaseDate = $this->workflowDate($input['desirePurchaseDate'] ?? null, 'desirePurchaseDate');
            $amount = $this->nonNegativeDecimal($input['amount'] ?? null, 'amount');
            $remark = $this->workflowOptionalString($input['remark'] ?? null, 65535);
            $org = $this->workflowOptionalString($input['org'] ?? null, 20);
            $productList = $this->workflowPurchaseProductList($input['productList'] ?? null);
            $productIds = array_values(array_unique(array_map(static fn (array $item): string => $item['productId'], $productList)));
            $this->assertProductsExist($productIds, $tenantId);

            $now = date('Y-m-d H:i:s');
            $orderId = $this->newId();
            Db::name('biz_purchase_order')->insert([
                'ID' => $orderId,
                'TITLE' => $title,
                'SETTLEMENT_STATUS' => self::SETTLEMENT_NOT_COMPLETED,
                'STORAGE_STATUS' => self::STORAGE_NOT_IN_WAREHOUSE,
                'SUPPLIER_ID' => $supplierId,
                'INSTANCE_ID' => $processInstanceId,
                'DESIRE_PURCHASE_DATE' => $desirePurchaseDate,
                'AMOUNT' => $amount,
                'REMARK' => $remark,
                'EXT_JSON' => json_encode(['supplier' => $supplier], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'DELETE_FLAG' => self::NOT_DELETE,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $operatorUserId !== '' ? $operatorUserId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'TENANT_ID' => $tenantId,
                'VERSION' => 0,
                'ORG' => $org,
            ]);

            $itemRows = [];
            foreach ($productList as $item) {
                $itemRows[] = [
                    'ID' => $this->newId(),
                    'PURCHASE_ORDER_ID' => $orderId,
                    'STORAGE_STATUS' => self::STORAGE_NOT_IN_WAREHOUSE,
                    'PRODUCT_ID' => $item['productId'],
                    'AMOUNT' => $item['amount'],
                    'NUMBER' => $item['number'],
                    'UNIT_AMOUNT' => $item['unitAmount'],
                    'DISCOUNT_RATE' => $item['discountRate'],
                    'REMARK' => $item['remark'],
                    'EXT_JSON' => null,
                    'DELETE_FLAG' => self::NOT_DELETE,
                    'CREATE_TIME' => $now,
                    'CREATE_USER' => $operatorUserId !== '' ? $operatorUserId : null,
                    'UPDATE_TIME' => null,
                    'UPDATE_USER' => null,
                    'TENANT_ID' => $tenantId,
                    'VERSION' => 0,
                    'FREIGHT_SHARE_AMOUNT' => '0',
                    'UNIT_COST_WITH_FREIGHT' => '0',
                ];
            }
            Db::name('biz_purchase_order_item')->insertAll($itemRows);

            return [
                'id' => $orderId,
                'instanceId' => $processInstanceId,
                'settlementStatus' => self::SETTLEMENT_NOT_COMPLETED,
                'storageStatus' => self::STORAGE_NOT_IN_WAREHOUSE,
                'amount' => $this->decimal($amount),
                'supplierId' => $supplierId,
                'itemCount' => count($itemRows),
                'existing' => false,
            ];
        });
    }

    private function workflowRequiredString(mixed $value, string $field, int $maxLength): string
    {
        $text = $this->workflowOptionalString($value, $maxLength);
        if ($text === null || $text === '') {
            throw new RuntimeException("missing {$field}", 400);
        }

        return $text;
    }

    private function workflowOptionalString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            $value = $value['id'] ?? $value['ID'] ?? $value['value'] ?? $value['key'] ?? '';
        }

        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length > $maxLength) {
            throw new RuntimeException('input is too long', 400);
        }

        return $text;
    }

    private function workflowDate(mixed $value, string $field): string
    {
        $text = $this->workflowRequiredString($value, $field, 40);
        if (is_numeric($text)) {
            $raw = (int)$text;
            $seconds = $raw > 9999999999 ? intdiv($raw, 1000) : $raw;
            return date('Y-m-d H:i:s', $seconds);
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowObject(mixed $value, string $field): array
    {
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                throw new RuntimeException("missing {$field}", 400);
            }
            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("invalid {$field}", 400);
            }
            $value = $decoded;
        }

        if (!is_array($value) || $value === []) {
            throw new RuntimeException("missing {$field}", 400);
        }

        return $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflowArrayList(mixed $value, string $field): array
    {
        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                throw new RuntimeException("missing {$field}", 400);
            }
            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                throw new RuntimeException("invalid {$field}", 400);
            }
            $value = $decoded;
        }

        if (!is_array($value) || $value === []) {
            throw new RuntimeException("missing {$field}", 400);
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new RuntimeException("invalid {$field} item", 400);
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<int, array{productId: string, amount: string, number: int, unitAmount: string, discountRate: string, remark: ?string}>
     */
    private function workflowPurchaseProductList(mixed $value): array
    {
        $items = $this->workflowArrayList($value, 'productList');
        $normalized = [];
        foreach ($items as $index => $item) {
            $productId = $this->workflowRequiredString(
                $item['productId'] ?? $item['PRODUCT_ID'] ?? null,
                "productList.{$index}.productId",
                20
            );

            $normalized[] = [
                'productId' => $productId,
                'amount' => $this->nonNegativeDecimal($item['amount'] ?? $item['AMOUNT'] ?? null, "productList.{$index}.amount"),
                'number' => $this->positiveInteger($item['number'] ?? $item['NUMBER'] ?? null, "productList.{$index}.number"),
                'unitAmount' => $this->nonNegativeDecimal($item['unitAmount'] ?? $item['UNIT_AMOUNT'] ?? null, "productList.{$index}.unitAmount"),
                'discountRate' => $this->nonNegativeDecimal($item['discountRate'] ?? $item['DISCOUNT_RATE'] ?? 0, "productList.{$index}.discountRate"),
                'remark' => $this->workflowOptionalString($item['remark'] ?? $item['REMARK'] ?? null, 255),
            ];
        }

        return $normalized;
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        $number = (float)$value;
        if ($number <= 0 || abs($number - round($number)) > 0.000001) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return (int)round($number);
    }

    private function stockInLockedOrder(
        array $order,
        string $warehouseId,
        ?string $remark,
        array $payload,
        ?string $processId = null,
        ?string $processCategory = null,
        ?string $operatorUserId = null
    ): array
    {
        $orderId = (string)$order['ID'];
        $storageStatus = trim((string)($order['STORAGE_STATUS'] ?? ''));
        if ($storageStatus !== self::STORAGE_NOT_IN_WAREHOUSE) {
            throw new RuntimeException('purchase order already in warehouse', 400);
        }

        $tenantId = trim((string)($order['TENANT_ID'] ?? ''));
        $warehouse = $this->activeWarehouse($warehouseId, $tenantId);
        $this->assertWarehouseWritable($warehouse, $payload, 'warehouse this purchase order');

        $items = $this->activeItemsByOrderForUpdate($orderId, $tenantId);
        if ($items === []) {
            throw new RuntimeException('purchase order item not found', 404);
        }

        $productIds = array_values(array_unique(array_map(
            static fn (array $item): string => (string)$item['PRODUCT_ID'],
            $items
        )));
        $this->assertProductsExist($productIds, $tenantId);

        $now = date('Y-m-d H:i:s');
        $userId = trim((string)($operatorUserId ?? $this->currentUserId($payload)));
        $effectiveProcessId = trim((string)($processId ?? self::PROCESS_SYS));
        $effectiveProcessCategory = trim((string)($processCategory ?? self::PROCESS_PROCURE_IN_WAREHOUSE));
        $deliveryIds = [];
        $inventoryIds = [];

        foreach ($items as $item) {
            $itemStorageStatus = trim((string)($item['STORAGE_STATUS'] ?? ''));
            if ($itemStorageStatus === self::STORAGE_IN_WAREHOUSE) {
                throw new RuntimeException('purchase order item already in warehouse', 400);
            }

            $amount = $this->positiveDecimal($item['NUMBER'] ?? null, 'number');
            $productId = (string)$item['PRODUCT_ID'];
            $deliveryId = $this->newId();
            $deliveryIds[] = $deliveryId;

            Db::name('delivery_record')->insert([
                'ID' => $deliveryId,
                'WAREHOUSES_ID' => $warehouseId,
                'PROCESS_ID' => $effectiveProcessId !== '' ? $effectiveProcessId : self::PROCESS_SYS,
                'PRODUCT_ID' => $productId,
                'AMOUNT' => $this->decimalStorage($amount),
                'CATEGORY' => self::DELIVERY_CATEGORY_IN,
                'PROCESS_CATEGORY' => $effectiveProcessCategory !== '' ? $effectiveProcessCategory : self::PROCESS_PROCURE_IN_WAREHOUSE,
                'OPERATOR' => $userId !== '' ? $userId : '0',
                'REMARK' => $remark ?? '',
                'DELIVERY_TIME' => $now,
                'CREATE_TIME' => $now,
                'CREATE_USER' => $userId !== '' ? $userId : null,
                'UPDATE_TIME' => null,
                'UPDATE_USER' => null,
                'DELETE_FLAG' => self::NOT_DELETE,
                'TENANT_ID' => $tenantId,
                'OBJECT_ID' => $orderId,
            ]);

            $inventoryIds[] = $this->increaseInventory($warehouseId, $productId, $tenantId, $amount, $now, $userId);

            Db::name('biz_purchase_order_item')
                ->where('ID', (string)$item['ID'])
                ->where('PURCHASE_ORDER_ID', $orderId)
                ->update([
                    'STORAGE_STATUS' => self::STORAGE_IN_WAREHOUSE,
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);
        }

        Db::name('biz_purchase_order')
            ->where('ID', $orderId)
            ->update([
                'STORAGE_STATUS' => self::STORAGE_IN_WAREHOUSE,
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
            ]);

        return [
            'id' => $orderId,
            'warehousesId' => $warehouseId,
            'storageStatus' => self::STORAGE_IN_WAREHOUSE,
            'deliveryIds' => $deliveryIds,
            'inventoryIds' => array_values(array_unique($inventoryIds)),
            'updatedItems' => count($items),
            'count' => 1,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eligibleWarehouseOrdersForUpdate(array $payload): array
    {
        $query = Db::name('biz_purchase_order')
            ->where('SETTLEMENT_STATUS', self::SETTLEMENT_COMPLETED)
            ->where('STORAGE_STATUS', self::STORAGE_NOT_IN_WAREHOUSE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        if (!$this->canSeeAll($payload)) {
            $scopeOrgIds = $this->scopeOrgIds($payload);
            if ($scopeOrgIds !== []) {
                $query->whereIn('ORG', $scopeOrgIds);
            } else {
                $currentUserId = $this->currentUserId($payload);
                if ($currentUserId === '') {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('CREATE_USER', $currentUserId);
                }
            }
        }

        return $query
            ->field('ID,SETTLEMENT_STATUS,STORAGE_STATUS,AMOUNT,CREATE_USER,TENANT_ID,ORG,VERSION')
            ->lock(true)
            ->order('ID', 'asc')
            ->select()
            ->toArray();
    }

    private function editPurchaseOrder(array $input, array $payload, bool $skipNormalEditGuards, string $action): array
    {
        $id = $this->requiredInput($input, 'id');
        $productList = $this->productList($input);

        return Db::transaction(function () use ($input, $payload, $id, $productList, $skipNormalEditGuards, $action): array {
            $order = $this->assertOrderWritable($id, $payload, $action);
            $tenantId = trim((string)($order['TENANT_ID'] ?? ''));
            if (!$skipNormalEditGuards) {
                $settlementStatus = trim((string)($order['SETTLEMENT_STATUS'] ?? ''));
                if ($settlementStatus === self::SETTLEMENT_COMPLETED) {
                    throw new RuntimeException('已结算订单不支持修改！', 400);
                }

                if ($this->goodsExpenditureAmount($id, $tenantId) > 0) {
                    throw new RuntimeException('该订单已有支出记录不支持修改！', 400);
                }
            }

            $items = $this->activeItemsForUpdate($id, $productList, $tenantId);
            $itemIds = array_map(static fn (array $item): string => (string)$item['id'], $productList);
            if (count($items) !== count($itemIds)) {
                throw new RuntimeException('purchase order item not found', 404);
            }

            $now = date('Y-m-d H:i:s');
            $userId = $this->currentUserId($payload);
            $updatedOrderFields = [
                'UPDATE_TIME' => $now,
                'UPDATE_USER' => $userId !== '' ? $userId : null,
                'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
            ];
            if (array_key_exists('amount', $input)) {
                $updatedOrderFields['AMOUNT'] = $this->nonNegativeDecimal($input['amount'], 'amount');
            }

            Db::name('biz_purchase_order')
                ->where('ID', $id)
                ->update($updatedOrderFields);

            $updatedItems = 0;
            foreach ($productList as $item) {
                $itemUpdate = [
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ];
                foreach ([
                    'amount' => ['AMOUNT', 'amount'],
                    'unitAmount' => ['UNIT_AMOUNT', 'unitAmount'],
                    'discountRate' => ['DISCOUNT_RATE', 'discountRate'],
                    'freightShareAmount' => ['FREIGHT_SHARE_AMOUNT', 'freightShareAmount'],
                    'unitCostWithFreight' => ['UNIT_COST_WITH_FREIGHT', 'unitCostWithFreight'],
                ] as $inputKey => [$column, $label]) {
                    if (array_key_exists($inputKey, $item)) {
                        $itemUpdate[$column] = $this->nonNegativeDecimal($item[$inputKey], $label);
                    }
                }

                Db::name('biz_purchase_order_item')
                    ->where('ID', (string)$item['id'])
                    ->where('PURCHASE_ORDER_ID', $id)
                    ->update($itemUpdate);
                $updatedItems++;
            }

            return [
                'id' => $id,
                'updatedItems' => $updatedItems,
                'amount' => $updatedOrderFields['AMOUNT'] ?? $order['AMOUNT'] ?? null,
            ];
        });
    }

    private function assertOrderWritable(string $id, array $payload, string $action): array
    {
        $row = $this->activeOrder($id, $payload);
        if ($this->canSeeAll($payload)) {
            return $row;
        }

        $orderOrg = trim((string)($row['ORG'] ?? ''));
        $scopeOrgIds = $this->scopeOrgIds($payload);
        if ($scopeOrgIds !== [] && $orderOrg !== '' && in_array($orderOrg, $scopeOrgIds, true)) {
            return $row;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($row['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return $row;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    private function activeOrder(string $id, array $payload): array
    {
        $query = Db::name('biz_purchase_order')
            ->where('ID', $id)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? $payload['tenantId'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query
            ->field('ID,SETTLEMENT_STATUS,STORAGE_STATUS,AMOUNT,CREATE_USER,TENANT_ID,ORG,VERSION')
            ->lock(true)
            ->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('purchase order not found', 404);
        }

        return $row;
    }

    private function goodsExpenditureAmount(string $orderId, string $tenantId): float
    {
        $query = Db::name('biz_expenditure_record')
            ->where('OBJECT_ID', $orderId)
            ->where('SETTLEMENT_CATEGORY', self::GOODS_EXPENDITURE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (float)$query->sum('AMOUNT');
    }

    private function activeDeliveryCountForOrder(string $orderId, string $tenantId): int
    {
        $query = Db::name('delivery_record')
            ->where('OBJECT_ID', $orderId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return (int)$query->count();
    }

    private function activeSupplier(string $supplierId, string $tenantId): array
    {
        $query = Db::name('supplier')
            ->where('ID', $supplierId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->field('ID,CREATE_USER,TENANT_ID,org,STATUS,NAME')->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('supplier not found', 404);
        }

        return $row;
    }

    private function assertSupplierWritable(array $supplier, array $payload, string $action): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $supplierOrg = trim((string)($supplier['org'] ?? $supplier['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && $supplierOrg !== '' && in_array($supplierOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $createUser = trim((string)($supplier['CREATE_USER'] ?? ''));
        if ($currentUserId !== '' && $createUser === $currentUserId) {
            return;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    private function assertCreateOrgWritable(?string $org, array $payload, string $action): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $orgId = trim((string)$org);
        if ($scopeOrgIds !== []) {
            if ($orgId !== '' && in_array($orgId, $scopeOrgIds, true)) {
                return;
            }

            throw new RuntimeException("no permission to {$action}", 403);
        }

        if ($this->currentUserId($payload) !== '') {
            return;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @param array<int, array<string, mixed>> $productList
     * @return array<string, array<string, mixed>>
     */
    private function activeItemsForUpdate(string $orderId, array $productList, string $tenantId): array
    {
        $ids = array_map(static fn (array $item): string => (string)$item['id'], $productList);
        $query = Db::name('biz_purchase_order_item')
            ->where('PURCHASE_ORDER_ID', $orderId)
            ->whereIn('ID', $ids)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query
            ->field('ID,PURCHASE_ORDER_ID,TENANT_ID')
            ->lock(true)
            ->select()
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            $items[(string)$row['ID']] = $row;
        }

        return $items;
    }

    private function activeWarehouse(string $warehouseId, string $tenantId): array
    {
        $query = Db::name('warehouses')
            ->where('ID', $warehouseId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $row = $query->find();
        if (!is_array($row) || $row === []) {
            throw new RuntimeException('warehouse not found', 404);
        }

        return $row;
    }

    private function assertWarehouseWritable(array $warehouse, array $payload, string $action): void
    {
        if ($this->canSeeAll($payload)) {
            return;
        }

        $scopeOrgIds = $this->scopeOrgIds($payload);
        $warehouseOrg = trim((string)($warehouse['ORG'] ?? ''));
        if ($scopeOrgIds !== [] && $warehouseOrg !== '' && in_array($warehouseOrg, $scopeOrgIds, true)) {
            return;
        }

        $currentUserId = $this->currentUserId($payload);
        $ownerUserId = trim((string)($warehouse['USER'] ?? ''));
        if ($currentUserId !== '' && $ownerUserId === $currentUserId) {
            return;
        }

        throw new RuntimeException("no permission to {$action}", 403);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activeItemsByOrderForUpdate(string $orderId, string $tenantId): array
    {
        $query = Db::name('biz_purchase_order_item')
            ->where('PURCHASE_ORDER_ID', $orderId)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        return $query
            ->field('ID,PURCHASE_ORDER_ID,STORAGE_STATUS,PRODUCT_ID,NUMBER,TENANT_ID,VERSION')
            ->lock(true)
            ->order('ID', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * @param array<int, string> $productIds
     */
    private function assertProductsExist(array $productIds, string $tenantId): void
    {
        if ($productIds === []) {
            throw new RuntimeException('missing product ids', 400);
        }

        $query = Db::name('biz_product')
            ->whereIn('ID', $productIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $found = array_map('strval', $query->column('ID'));
        if (count(array_unique($found)) !== count(array_unique($productIds))) {
            throw new RuntimeException('invalid product ids', 400);
        }
    }

    private function increaseInventory(string $warehouseId, string $productId, string $tenantId, string $amount, string $now, string $userId): string
    {
        $query = Db::name('inventory')
            ->where('WAREHOUSES_ID', $warehouseId)
            ->where('PRODUCT_ID', $productId);
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $inventory = $query
            ->field('ID,CURRENT_COUNT,DELETE_FLAG,VERSION')
            ->lock(true)
            ->find();

        if (is_array($inventory) && $inventory !== []) {
            $deleteFlag = trim((string)($inventory['DELETE_FLAG'] ?? ''));
            if ($deleteFlag !== '' && $deleteFlag !== self::NOT_DELETE) {
                throw new RuntimeException('inventory unique key conflicts with deleted row', 409);
            }

            $current = (float)$this->decimalString($inventory['CURRENT_COUNT'] ?? '0');
            $next = $current + (float)$amount;
            Db::name('inventory')
                ->where('ID', (string)$inventory['ID'])
                ->update([
                    'CURRENT_COUNT' => $this->decimalStorage($next),
                    'UPDATE_TIME' => $now,
                    'UPDATE_USER' => $userId !== '' ? $userId : null,
                    'VERSION' => Db::raw('COALESCE(VERSION, 0) + 1'),
                ]);

            return (string)$inventory['ID'];
        }

        $inventoryId = $this->newId();
        Db::name('inventory')->insert([
            'ID' => $inventoryId,
            'WAREHOUSES_ID' => $warehouseId,
            'PRODUCT_ID' => $productId,
            'CURRENT_COUNT' => $this->decimalStorage($amount),
            'DELETE_FLAG' => self::NOT_DELETE,
            'CREATE_TIME' => $now,
            'CREATE_USER' => $userId !== '' ? $userId : null,
            'UPDATE_TIME' => null,
            'UPDATE_USER' => null,
            'TENANT_ID' => $tenantId,
            'VERSION' => 0,
        ]);

        return $inventoryId;
    }

    private function orderQuery(array $filters, array $payload)
    {
        $query = Db::name('biz_purchase_order')
            ->alias('o')
            ->leftJoin('sys_org org', 'org.ID = o.ORG')
            ->where(function ($query): void {
                $query->whereNull('o.DELETE_FLAG')->whereOr('o.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($filters['tenantId'] ?? $payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('o.TENANT_ID', $tenantId);
        }

        foreach ([
            'id' => 'o.ID',
            'settlementStatus' => 'o.SETTLEMENT_STATUS',
            'storageStatus' => 'o.STORAGE_STATUS',
            'supplierId' => 'o.SUPPLIER_ID',
            'instanceId' => 'o.INSTANCE_ID',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, (string)$filters[$filter]);
            }
        }

        if (!empty($filters['orgId'])) {
            $orgIds = $this->orgAndChildren((string)$filters['orgId']);
            if ($orgIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('o.ORG', $orgIds);
            }
        } elseif (!empty($payload['data_scope_org_ids']) && is_array($payload['data_scope_org_ids'])) {
            $query->whereIn('o.ORG', array_map('strval', $payload['data_scope_org_ids']));
        }

        if (!empty($filters['supplierName'])) {
            $query->whereRaw(
                "(JSON_VALID(o.EXT_JSON) = 1 AND JSON_UNQUOTE(JSON_EXTRACT(o.EXT_JSON, '$.supplier.name')) LIKE ?)",
                ['%' . trim((string)$filters['supplierName']) . '%']
            );
        }

        if (!empty($filters['searchKey'])) {
            $keyword = '%' . trim((string)$filters['searchKey']) . '%';
            $query->whereRaw(
                "(o.TITLE LIKE ? OR o.REMARK LIKE ? OR (JSON_VALID(o.EXT_JSON) = 1 AND JSON_UNQUOTE(JSON_EXTRACT(o.EXT_JSON, '$.supplier.name')) LIKE ?))",
                [$keyword, $keyword, $keyword]
            );
        }

        if (!empty($filters['productName'])) {
            $orderIds = $this->purchaseOrderIdsByProductName((string)$filters['productName'], $tenantId);
            if ($orderIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('o.ID', $orderIds);
            }
        }

        if (!empty($filters['minAmount'])) {
            $query->where('o.AMOUNT', '>=', (float)$filters['minAmount']);
        }

        if (!empty($filters['maxAmount'])) {
            $query->where('o.AMOUNT', '<=', (float)$filters['maxAmount']);
        }

        $this->applyCreateTimeRange($query, $filters);

        return $query;
    }

    private function applyCreateTimeRange($query, array $filters): void
    {
        $start = trim((string)($filters['startCreateTime'] ?? ''));
        $end = trim((string)($filters['endCreateTime'] ?? ''));
        if ($start !== '' && $end !== '') {
            $query->whereBetweenTime('o.CREATE_TIME', $start, $end);
        } elseif ($start !== '') {
            $query->whereTime('o.CREATE_TIME', '>=', $start);
        } elseif ($end !== '') {
            $query->whereTime('o.CREATE_TIME', '<=', $end);
        }
    }

    private function applySort($query, array $filters)
    {
        $sortField = (string)($filters['sortField'] ?? '');
        $sortOrder = strtolower((string)($filters['sortOrder'] ?? ''));
        if ($sortField !== '' && isset(self::SORT_FIELD_MAP[$sortField])) {
            $direction = in_array($sortOrder, ['desc', 'descend', 'descending'], true) ? 'desc' : 'asc';

            return $query->order(self::SORT_FIELD_MAP[$sortField], $direction)->order('o.ID', 'asc');
        }

        return $query->order('o.ID', 'asc');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function orderRows(array $rows): array
    {
        return array_map(fn (array $row): array => $this->orderRow($row), $rows);
    }

    private function orderRow(array $row): array
    {
        $extJson = (string)($this->value($row, 'EXT_JSON', 'extJson') ?? '');
        $ext = $this->decodeJsonObject($extJson);
        $supplier = is_array($ext['supplier'] ?? null) ? $ext['supplier'] : [];

        return [
            'id' => $this->value($row, 'ID', 'id'),
            'title' => $this->value($row, 'TITLE', 'title'),
            'settlementStatus' => $this->value($row, 'SETTLEMENT_STATUS', 'settlementStatus'),
            'storageStatus' => $this->value($row, 'STORAGE_STATUS', 'storageStatus'),
            'supplierId' => $this->value($row, 'SUPPLIER_ID', 'supplierId'),
            'supplierName' => $supplier['name'] ?? null,
            'supplier' => $supplier,
            'instanceId' => $this->value($row, 'INSTANCE_ID', 'instanceId'),
            'desirePurchaseDate' => $this->value($row, 'DESIRE_PURCHASE_DATE', 'desirePurchaseDate'),
            'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
            'remark' => $this->value($row, 'REMARK', 'remark'),
            'extJson' => $extJson,
            'ext' => $ext,
            'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
            'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
            'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
            'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
            'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
            'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
            'version' => $this->integer($this->value($row, 'VERSION', 'version')),
            'org' => $this->value($row, 'ORG', 'org'),
            'orgName' => $this->value($row, 'ORG_NAME', 'orgName'),
        ];
    }

    /**
     * @param array<int, string|null> $orderIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function itemsByOrderIds(array $orderIds, array $payload): array
    {
        $ids = array_values(array_filter(array_map(static fn ($id): string => trim((string)$id), $orderIds)));
        if ($ids === []) {
            return [];
        }

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $query = $this->itemBaseQuery($tenantId)->whereIn('i.PURCHASE_ORDER_ID', $ids);
        $rows = $query->order('i.ID', 'asc')->select()->toArray();
        $result = [];
        foreach ($this->itemRows($rows) as $row) {
            $result[(string)$row['purchaseOrderId']][] = $row;
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemRowsByOrderId(string $orderId, array $payload): array
    {
        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        $rows = $this->itemBaseQuery($tenantId)
            ->where('i.PURCHASE_ORDER_ID', $orderId)
            ->order('i.ID', 'asc')
            ->select()
            ->toArray();

        return $this->itemRows($rows);
    }

    private function itemBaseQuery(string $tenantId)
    {
        $query = Db::name('biz_purchase_order_item')
            ->alias('i')
            ->leftJoin('biz_product p', 'p.ID = i.PRODUCT_ID')
            ->field(self::ITEM_FIELDS)
            ->where(function ($query): void {
                $query->whereNull('i.DELETE_FLAG')->whereOr('i.DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $query->where('i.TENANT_ID', $tenantId);
        }

        return $query;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function itemRows(array $rows): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'purchaseOrderId' => $this->value($row, 'PURCHASE_ORDER_ID', 'purchaseOrderId'),
                'storageStatus' => $this->value($row, 'STORAGE_STATUS', 'storageStatus'),
                'productId' => $this->value($row, 'PRODUCT_ID', 'productId'),
                'productName' => $this->value($row, 'PRODUCT_NAME', 'productName'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'number' => $this->integer($this->value($row, 'NUMBER', 'number')),
                'unitAmount' => $this->decimal($this->value($row, 'UNIT_AMOUNT', 'unitAmount')),
                'discountRate' => $this->decimal($this->value($row, 'DISCOUNT_RATE', 'discountRate')),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'extJson' => $this->value($row, 'EXT_JSON', 'extJson'),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'version' => $this->integer($this->value($row, 'VERSION', 'version')),
                'freightShareAmount' => $this->decimal($this->value($row, 'FREIGHT_SHARE_AMOUNT', 'freightShareAmount')),
                'unitCostWithFreight' => $this->decimal($this->value($row, 'UNIT_COST_WITH_FREIGHT', 'unitCostWithFreight')),
                'productCategory' => $this->value($row, 'PRODUCT_CATEGORY', 'productCategory'),
                'category' => $this->value($row, 'CATEGORY', 'category'),
                'specs' => $this->value($row, 'SPECS', 'specs'),
                'purchasePrice' => $this->decimal($this->value($row, 'PURCHASE_PRICE', 'purchasePrice')),
                'salePrice' => $this->decimal($this->value($row, 'SALE_PRICE', 'salePrice')),
                'minPrice' => $this->decimal($this->value($row, 'MIN_PRICE', 'minPrice')),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expenditureRows(string $orderId, array $payload): array
    {
        $query = Db::name('biz_expenditure_record')
            ->where('OBJECT_ID', $orderId)
            ->where('SETTLEMENT_CATEGORY', self::GOODS_EXPENDITURE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        $tenantId = trim((string)($payload['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $query->where('TENANT_ID', $tenantId);
        }

        $rows = $query->order('CREATE_TIME', 'asc')->select()->toArray();

        return array_map(function (array $row): array {
            return [
                'id' => $this->value($row, 'ID', 'id'),
                'objectId' => $this->value($row, 'OBJECT_ID', 'objectId'),
                'targetId' => $this->value($row, 'TARGET_ID', 'targetId'),
                'serialId' => $this->value($row, 'SERIAL_ID', 'serialId'),
                'processId' => $this->value($row, 'PROCESS_ID', 'processId'),
                'settlementCategory' => $this->value($row, 'SETTLEMENT_CATEGORY', 'settlementCategory'),
                'payer' => $this->value($row, 'PAYER', 'payer'),
                'bankName' => $this->value($row, 'BANK_NAME', 'bankName'),
                'bankAccount' => $this->value($row, 'BANK_ACCOUNT', 'bankAccount'),
                'remark' => $this->value($row, 'REMARK', 'remark'),
                'payerTime' => $this->value($row, 'PAYER_TIME', 'payerTime'),
                'amount' => $this->decimal($this->value($row, 'AMOUNT', 'amount')),
                'deleteFlag' => $this->value($row, 'DELETE_FLAG', 'deleteFlag'),
                'createTime' => $this->value($row, 'CREATE_TIME', 'createTime'),
                'createUser' => $this->value($row, 'CREATE_USER', 'createUser'),
                'updateTime' => $this->value($row, 'UPDATE_TIME', 'updateTime'),
                'updateUser' => $this->value($row, 'UPDATE_USER', 'updateUser'),
                'tenantId' => $this->value($row, 'TENANT_ID', 'tenantId'),
                'user' => $this->value($row, 'USER', 'user'),
                'org' => $this->value($row, 'ORG', 'org'),
            ];
        }, $rows);
    }

    /**
     * @return array<int, string>
     */
    private function purchaseOrderIdsByProductName(string $productName, string $tenantId): array
    {
        $keyword = trim($productName);
        if ($keyword === '') {
            return [];
        }

        $productQuery = Db::name('biz_product')
            ->whereLike('PRODUCT_NAME', '%' . $keyword . '%')
            ->where('CATEGORY', self::PRODUCT_CATEGORY_SINGLE)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $productQuery->where('TENANT_ID', $tenantId);
        }

        $productIds = array_map('strval', $productQuery->column('ID'));
        if ($productIds === []) {
            return [];
        }

        $itemQuery = Db::name('biz_purchase_order_item')
            ->whereIn('PRODUCT_ID', $productIds)
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            });

        if ($tenantId !== '') {
            $itemQuery->where('TENANT_ID', $tenantId);
        }

        return array_values(array_unique(array_map('strval', $itemQuery->column('PURCHASE_ORDER_ID'))));
    }

    /**
     * @return array<int, string>
     */
    private function orgAndChildren(string $orgId): array
    {
        $orgId = trim($orgId);
        if ($orgId === '') {
            return [];
        }

        $rows = Db::name('sys_org')
            ->where(function ($query): void {
                $query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', self::NOT_DELETE);
            })
            ->field(['ID', 'PARENT_ID'])
            ->select()
            ->toArray();

        $childrenByParent = [];
        foreach ($rows as $row) {
            $childrenByParent[(string)($row['PARENT_ID'] ?? '')][] = (string)$row['ID'];
        }

        $result = [];
        $queue = [$orgId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || in_array($current, $result, true)) {
                continue;
            }

            $result[] = $current;
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $result;
    }

    private function pagination(array $filters): array
    {
        $page = max(1, (int)($filters['current'] ?? $filters['page'] ?? $filters['pageNo'] ?? 1));
        $limit = max(1, min(200, (int)($filters['size'] ?? $filters['limit'] ?? $filters['pageSize'] ?? 20)));

        return [$page, $limit];
    }

    private function currentUserId(array $payload): string
    {
        return trim((string)($payload['user_id'] ?? $payload['userId'] ?? $payload['id'] ?? ''));
    }

    /**
     * @return array<int, string>
     */
    private function scopeOrgIds(array $payload): array
    {
        $ids = [];
        $direct = $payload['data_scope_org_ids'] ?? [];
        if (is_string($direct)) {
            $direct = explode(',', $direct);
        }
        if (is_array($direct)) {
            $ids = array_merge($ids, $direct);
        }

        $scopes = $payload['data_scopes'] ?? $payload['dataScopeList'] ?? [];
        if (is_array($scopes)) {
            $ids = array_merge($ids, array_map(static function (mixed $scope): string {
                if (!is_array($scope)) {
                    return '';
                }

                return trim((string)($scope['orgId'] ?? $scope['org_id'] ?? ''));
            }, $scopes));
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string)$id),
            $ids
        ))));
    }

    private function canSeeAll(array $payload): bool
    {
        $account = strtolower((string)($payload['account'] ?? ''));
        if (in_array($account, ['bizadmin', 'superadmin'], true)) {
            return true;
        }

        $roleCodes = $payload['role_codes'] ?? $payload['roleCodeList'] ?? [];
        if (!is_array($roleCodes)) {
            return false;
        }

        foreach ($roleCodes as $roleCode) {
            if (in_array(strtolower((string)$roleCode), ['superadmin', 'tenantadmin', 'bizadmin'], true)) {
                return true;
            }
        }

        return false;
    }

    private function requiredInput(array $input, string $key): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new RuntimeException("missing {$key}", 400);
        }

        return $value;
    }

    private function tenantId(array $input, array $payload): string
    {
        $tenantId = trim((string)($input['tenantId'] ?? $input['tenant_id'] ?? $payload['tenant_id'] ?? $payload['tenantId'] ?? ''));

        return $tenantId !== '' ? $tenantId : '1';
    }

    private function defaultOrgId(array $payload): ?string
    {
        $orgId = trim((string)($payload['org_id'] ?? $payload['orgId'] ?? $payload['org'] ?? ''));

        return $orgId !== '' ? $orgId : null;
    }

    /**
     * @param array<int, array{amount: string}> $productList
     */
    private function productAmountTotal(array $productList): string
    {
        $total = 0.0;
        foreach ($productList as $item) {
            $total += (float)$item['amount'];
        }

        return number_format($total, 2, '.', '');
    }

    /**
     * @param array<int, mixed> $source
     * @return array<int, string>
     */
    private function stringList(array $source): array
    {
        $values = [];
        foreach ($source as $entry) {
            if (is_array($entry)) {
                $entry = $entry['id'] ?? $entry['ID'] ?? '';
            }
            foreach (explode(',', (string)$entry) as $part) {
                $value = trim($part);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productList(array $input): array
    {
        $items = $input['productList'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('missing productList', 400);
        }

        $normalized = [];
        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('invalid productList item', 400);
            }
            $id = trim((string)($item['id'] ?? ''));
            if ($id === '') {
                throw new RuntimeException('missing product item id', 400);
            }
            $item['id'] = $id;
            $ids[] = $id;
            $normalized[] = $item;
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw new RuntimeException('duplicate product item id', 400);
        }

        return $normalized;
    }

    private function nonNegativeDecimal(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        $number = (float)$value;
        if ($number < 0) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return number_format($number, 2, '.', '');
    }

    private function positiveDecimal(mixed $value, string $field): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        $number = (float)$value;
        if ($number <= 0) {
            throw new RuntimeException("invalid {$field}", 400);
        }

        return $this->decimalString($number);
    }

    private function decimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.000000';
        }
        if (!is_numeric($value)) {
            throw new RuntimeException('invalid decimal', 400);
        }

        return number_format((float)$value, 6, '.', '');
    }

    private function decimalStorage(string|float $value): string
    {
        return rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.') ?: '0';
    }

    private function newId(): string
    {
        return (string)((int)floor(microtime(true) * 1000)) . (string)random_int(100000, 999999);
    }

    private function decodeJsonObject(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function decimal(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float)$value;

        return fmod($number, 1.0) === 0.0 ? (int)$number : $number;
    }

    private function value(array $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }
}
