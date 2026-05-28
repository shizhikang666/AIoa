<?php

namespace app\model;

/**
 * Payment collection record model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizpaymentrecord.entity.BizPaymentRecord
 * Table: biz_payment_record
 *
 * Relation notes:
 * - OBJECT_ID points to the business object being paid.
 * - TARGET_ID points to the settlement account.
 * - PROCESS_ID points to a workflow process id.
 * - USER and ORG record ownership.
 *
 * @property string $ID 主键
 * @property string $OBJECT_ID 对象编号
 * @property string $TARGET_ID 收款账号编号
 * @property string $SERIAL_ID 流水编号
 * @property string $PROCESS_ID 流程实例编号
 * @property string $SETTLEMENT_CATEGORY 结算分类
 * @property string|null $PAYER 打款款人
 * @property string|null $BANK_NAME 打款银行行
 * @property string|null $BANK_ACCOUNT 银行账户
 * @property string|null $REMARK 备注
 * @property string|null $PAYER_TIME 打款时间
 * @property string|float $AMOUNT 打款金额
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property string|null $USER 所属用户
 * @property string|null $ORG 所属组织
 */
class BizPaymentRecord extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_payment_record';
}

