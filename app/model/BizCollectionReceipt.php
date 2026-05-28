<?php

namespace app\model;

/**
 * Collection receipt model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizcollectionreceipt.entity.BizCollectionReceipt
 * Table: biz_collection_receipt
 *
 * Relation notes:
 * - PAYMENT_RECORD_ID points to biz_payment_record.ID.
 * - account fields in the Java entity are translation-only and are not physical columns.
 *
 * @property string $ID
 * @property string|null $PAYMENT_RECORD_ID
 * @property string|null $REMARK
 * @property string|null $PLAY_STATUS
 * @property string|float $AMOUNT
 * @property string|float $SETTLEMENT_AMOUNT
 * @property int $VERSION
 * @property string $TENANT_ID
 */
class BizCollectionReceipt extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_collection_receipt';
}
