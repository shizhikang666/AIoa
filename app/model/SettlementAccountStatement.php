<?php

namespace app\model;

/**
 * Settlement account statement model.
 *
 * Java entity: vip.xiaonuo.biz.modular.settlementaccount.entity.SettlementAccountStatement
 * Table: settlement_account_statement
 *
 * Relation notes:
 * - ACCOUNT_ID points to settlement_account.ID.
 * - PROCESS_ID stores the related process/business operation id.
 *
 * @property string $ID
 * @property string $ACCOUNT_ID
 * @property string $PROCESS_ID
 * @property string|float $AFTER_AMOUNT
 * @property string|float $BEFORE_AMOUNT
 * @property string|float $AMOUNT
 * @property string $SETTLEMENT_TYPE
 * @property string $SETTLEMENT_CATEGORY
 * @property string $PROCESS_CATEGORY
 * @property string|null $PAYER_TIME
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class SettlementAccountStatement extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'settlement_account_statement';
}
