<?php

namespace app\model;

/**
 * Purchase order header model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizpurchaseorder.entity.BizPurchaseOrder
 * Table: biz_purchase_order
 *
 * Relation notes:
 * - SUPPLIER_ID points to supplier.ID.
 * - INSTANCE_ID points to workflow runtime instance id.
 * - ORG points to sys_org.ID.
 * - EXT_JSON stores product JSON in the Java implementation.
 *
 * @property string $ID 主键
 * @property string $TITLE 标题
 * @property string $SETTLEMENT_STATUS 结算状态
 * @property string $STORAGE_STATUS 入库状态
 * @property string $SUPPLIER_ID 供应商编号
 * @property string $INSTANCE_ID 流程实例ID
 * @property string|null $DESIRE_PURCHASE_DATE 预期采购时间
 * @property string|float $AMOUNT 金额
 * @property string|null $REMARK 备注
 * @property string|null $EXT_JSON 扩展信息,存放产品json
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 * @property string|null $ORG 所属组织组织
 */
class BizPurchaseOrder extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_purchase_order';
}

