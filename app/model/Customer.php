<?php

namespace app\model;

/**
 * Customer model.
 *
 * Java entity: vip.xiaonuo.biz.modular.customer.entity.Customer
 * Table: customer
 *
 * Relation notes:
 * - ORG points to sys_org.ID.
 * - USER points to sys_user.ID.
 * - FILE_ID usually points to dev_file.ID.
 * - remark is lower-case in SQL and must remain unchanged.
 *
 * Sensitive Java fields using SM4 type handlers: PHONE, DETAILS_ADDRESS.
 *
 * @property string $ID 主键
 * @property string|null $NAME 客户名称
 * @property string|null $CONTACTS 联系人
 * @property string|null $PHONE 联系电话
 * @property string|null $DETAILS_ADDRESS 详细地址
 * @property string|null $ADDRESS 客户地区
 * @property string|null $SOURCE_TYPE 客户来源
 * @property string|null $CUSTOM_TYPE 客户类型
 * @property string|null $ORG 所属组织组织
 * @property string|null $USER 所属用户
 * @property string|null $STATUS 状态
 * @property int|null $SORT_CODE 排序编码
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $FILE_ID 营业执照
 * @property int $VERSION 版本号乐观锁标记
 * @property string|float $DEAL_AMOUNT 成交次数
 * @property string|null $remark 备注
 * @property string|null $FIRST_CONTACT_TIME 首次联系时间
 * @property string $TENANT_ID 租户id
 */
class Customer extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'customer';
}

