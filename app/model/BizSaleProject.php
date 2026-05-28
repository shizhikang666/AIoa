<?php

namespace app\model;

/**
 * Sales project model.
 *
 * Java entity: vip.xiaonuo.biz.modular.saleproject.entity.BizSaleProject
 * Table: biz_sale_project
 *
 * Relation notes:
 * - CUSTOMER points to customer.ID.
 * - USER points to sys_user.ID.
 * - ORG points to sys_org.ID.
 * - PROCESS_ID points to a workflow process id.
 * - ACCOUNT_ID points to a settlement account.
 * - special_type is lower-case in SQL and must remain unchanged.
 *
 * @property string $ID 主键
 * @property string $CUSTOMER 项目所属客户编号
 * @property string $PROJECT_NAME 项目名称
 * @property string $PROJECT_STATE 项目状态
 * @property string $PLAY_STATE 付款状态
 * @property string $VISIBILITY 项目显示状态
 * @property string|float $INIT_PRICE 订单初始金额
 * @property string|float $TOTAL_PRICE 累计金额
 * @property string|float $AMOUNT_COLLECTED 累计收款金额
 * @property string $PROJECT_CATEGORY 类别直采或默认
 * @property string|null $USER 项目负责人
 * @property string|null $ORG 项目所属组织
 * @property string|null $REMARK 备注
 * @property string|null $PROCESS_ID 流程编号
 * @property string|null $ACCOUNT_ID 结算账户
 * @property string|null $PAYER_CATEGORY 结算方式
 * @property string|null $FREIGHT_CATEGORY 运费付款方式
 * @property string|float|null $FREIGHT 运费
 * @property string|null $PROJECT_CODE 项目编号
 * @property string|null $COMPLETION_DATE 成交日期
 * @property string|float|null $REBATE_AMOUNT 回扣金额
 * @property string|float|null $TOTAL_RETURN_AMOUNT 累计退货金额
 * @property string|float|null $TOTAL_REFUND_AMOUNT 累计退款金额
 * @property string|null $REPEAL_CONTENT 作废原因
 * @property string|null $special_type 特殊订单类型
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 */
class BizSaleProject extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_sale_project';
}

