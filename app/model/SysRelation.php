<?php

namespace app\model;

/**
 * System relation model.
 *
 * Java entity: vip.xiaonuo.sys.modular.relation.entity.SysRelation
 * Table: sys_relation
 *
 * Relation notes:
 * - OBJECT_ID is the owning user/role/resource id depending on CATEGORY.
 * - TARGET_ID is the target role/resource/mobile resource/API URL depending on CATEGORY.
 * - EXT_JSON stores grant details such as button ids and data scope.
 *
 * Known categories:
 * SYS_USER_WORKBENCH_DATA, SYS_USER_HAS_RESOURCE, SYS_USER_HAS_PERMISSION,
 * SYS_USER_HAS_ROLE, SYS_ROLE_HAS_RESOURCE, SYS_ROLE_HAS_MOBILE_MENU,
 * SYS_ROLE_HAS_PERMISSION.
 *
 * @property string $ID 主键
 * @property string|null $OBJECT_ID 对象ID
 * @property string|null $TARGET_ID 目标ID
 * @property string|null $CATEGORY 分类
 * @property string|null $EXT_JSON 扩展信息
 */
class SysRelation extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sys_relation';
}

