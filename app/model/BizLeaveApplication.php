<?php

namespace app\model;

/**
 * Leave application workflow form model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizleaveapplication.entity.BizLeaveApplication
 * Table: biz_leave_application
 *
 * Relation notes:
 * - USER_ID points to sys_user.ID.
 * - PROCESS_ID points to a workflow process id.
 * - OBJECT_ID can point to the related source business document.
 * - category is lower-case in SQL and must remain unchanged.
 *
 * @property string $ID 主键
 * @property string $USER_ID 请假userId
 * @property string $PROCESS_ID 流程iD
 * @property string $category 请假类型
 * @property string|float $AMOUNT 天数
 * @property string|null $REMARK 请假原因
 * @property string $START_TIME 请假开始日期
 * @property string $END_TIME 请假结束日期
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property string|null $OBJECT_ID 关联单号id
 */
class BizLeaveApplication extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_leave_application';
}

