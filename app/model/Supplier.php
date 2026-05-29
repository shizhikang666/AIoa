<?php

namespace app\model;

/**
 * Supplier model.
 *
 * Java entity: vip.xiaonuo.biz.modular.supplier.entity.Supplier
 * Table: supplier
 *
 * Relation notes:
 * - org is lower-case in SQL and points to sys_org.ID.
 * - TENANT_ID points to tenants.Tenant_ID.
 *
 * @property string $ID 主键
 * @property string|null $NAME 供应商名称
 * @property string|null $CONTACTS 联系人
 * @property string|null $PHONE 联系电话
 * @property string|null $BANK_NAME 开户行
 * @property string|null $BANK_ACCOUNT 银行账户
 * @property string|null $STATUS 供应商状态
 * @property string|null $ENTERPRISE_NATURE 企业性质
 * @property string|null $TAX_REGISTRATION_NUMBER 税务登记号
 * @property string|null $PAYMENT_METHOD 结算方式
 * @property int|null $SORT_CODE 排序编码
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string|null $EXT_JSON 扩展信息
 * @property string $TENANT_ID 租户id
 * @property string|null $ALIAS_NAME 账号别名
 * @property string|null $org 所属部门
 */
class Supplier extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'supplier';
}

