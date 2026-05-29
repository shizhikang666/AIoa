<?php

namespace app\model;

/**
 * System resource model.
 *
 * Java entities:
 * - vip.xiaonuo.sys.modular.resource.entity.SysModule
 * - vip.xiaonuo.sys.modular.resource.entity.SysMenu
 * - vip.xiaonuo.sys.modular.resource.entity.SysButton
 *
 * Table: sys_resource
 *
 * Relation notes:
 * - PARENT_ID points to sys_resource.ID for menu/catalog/button trees.
 * - MODULE points to the module resource id.
 * - CATEGORY separates MODULE, MENU, and BUTTON records.
 * - Role/user grants are stored in sys_relation.
 *
 * @property string $ID 主键
 * @property string|null $PARENT_ID 父id
 * @property string|null $TITLE 标题
 * @property string|null $NAME 别名
 * @property string|null $CODE 编码
 * @property string|null $CATEGORY 分类
 * @property string|null $MODULE 模块
 * @property string|null $MENU_TYPE 菜单类型
 * @property string|null $PATH 路径
 * @property string|null $COMPONENT 组件
 * @property string|null $ICON 图标
 * @property string|null $COLOR 颜色
 * @property string|null $VISIBLE 是否可见
 * @property int|null $SORT_CODE 排序码
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 */
class SysResource extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sys_resource';
}

