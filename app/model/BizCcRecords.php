<?php

namespace app\model;

/**
 * Business workflow CC record model.
 *
 * Java entity: vip.xiaonuo.biz.modular.ccrecords.entity.BizCcRecords
 * Table: biz_cc_records
 *
 * Relation notes:
 * - PROCESS_ID and INSTANCE_ID point to workflow process identifiers.
 * - PROMOTER_ID and USER point to sys_user.ID values.
 * - TENANT_ID points to tenants.Tenant_ID.
 *
 * @property string $ID 主键
 * @property string $TITLE 标题
 * @property string $PROCESS_ID 流程iD
 * @property string $PROMOTER_ID 发起人ID
 * @property string $INSTANCE_ID 流程实例ID
 * @property string $CATEGORY 流程类别
 * @property string|null $EXT_JSON 扩展信息,存放json
 * @property string|null $USER 抄送用户
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 */
class BizCcRecords extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_cc_records';
}

