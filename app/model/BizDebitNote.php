<?php

namespace app\model;

/**
 * Debit note model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizdebitnote.entity.BizDebitNote
 * Table: biz_debit_note
 *
 * Relation notes:
 * - EXPENDITURE_RECORD_ID points to biz_expenditure_record.ID.
 * - ORG points to sys_org.ID.
 * - account fields in the Java entity are translation-only and are not physical columns.
 *
 * @property string $ID
 * @property string|null $EXPENDITURE_RECORD_ID
 * @property string|null $REMARK
 * @property string|null $PLAY_STATUS
 * @property string|float $AMOUNT
 * @property string|float $SETTLEMENT_AMOUNT
 * @property int $VERSION
 * @property string|null $ORG
 * @property string|float $HISTORY_AMOUNT
 * @property string $TENANT_ID
 */
class BizDebitNote extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_debit_note';
}
