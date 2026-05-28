<?php

namespace app\model;

/**
 * Team project task model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizteamprojecttask.entity.BizTeamProjectTask
 * Table: biz_team_project_task
 *
 * Relation notes:
 * - TEAM_PROJECT_ID points to biz_team_project.ID.
 * - TEAM_PROJECT_TASK_CATEGORY_ID points to biz_team_project_task_category.ID.
 *
 * @property string $ID 主键
 * @property string $TEAM_PROJECT_ID 团队编号
 * @property string $TEAM_PROJECT_TASK_CATEGORY_ID 分类id
 * @property string|null $STATUS 事项状态
 * @property string|null $TITLE 任务标题
 * @property int|null $PROGRESS 任务进度
 * @property string|null $CONTENT_TEXT 任务目标
 * @property string|null $DELETE_FLAG 删除标志
 * @property int|null $SORT_CODE 排序码
 * @property string|null $EXT_JSON 扩展内容
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 * @property int $VERSION 版本号乐观锁标记
 */
class BizTeamProjectTask extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_team_project_task';
}

