<?php

namespace app\model;

/**
 * Settlement account model.
 *
 * Java entity: vip.xiaonuo.biz.modular.settlementaccount.entity.SettlementAccount
 * Table: settlement_account
 *
 * Relation notes:
 * - org is lower-case in SQL and must remain unchanged.
 * - Statements live in settlement_account_statement.
 *
 * @property string $ID
 * @property string|null $ACCOUNT_NAME
 * @property string|null $ACCOUNT_NUMBER
 * @property string|float $INITIAL_AMOUNT
 * @property string|float $CURRENT_AMOUNT
 * @property string|null $ACCOUNT_STATUS
 * @property int|null $SORT_CODE
 * @property string|null $EXT_JSON
 * @property int $VERSION
 * @property string|null $org
 * @property string|float $ARCHIVE_AMOUNT
 * @property string|null $ARCHIVE_TIME
 * @property string $TENANT_ID
 */
class SettlementAccount extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'settlement_account';
}
