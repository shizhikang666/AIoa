<?php

namespace app\model;

/**
 * Mobile resource model.
 *
 * Java entities:
 * - vip.xiaonuo.mobile.modular.resource.entity.MobileModule
 * - vip.xiaonuo.mobile.modular.resource.entity.MobileMenu
 * - vip.xiaonuo.mobile.modular.resource.entity.MobileButton
 *
 * Table: mobile_resource
 *
 * Relation notes:
 * - PARENT_ID points to mobile_resource.ID.
 * - MODULE points to a mobile module resource id.
 * - CATEGORY separates MODULE, MENU, and BUTTON records.
 * - Role mobile grants are stored in sys_relation with SYS_ROLE_HAS_MOBILE_MENU.
 *
 * @property string $ID 主键
 * @property string|null $PARENT_ID 父ID
 * @property string|null $TITLE 名称
 * @property string|null $CODE 编码
 * @property string|null $CATEGORY 分类
 * @property string|null $MODULE 模块
 * @property string|null $MENU_TYPE 菜单类型
 * @property string|null $PATH 路径
 * @property string|null $ICON 图标
 * @property string|null $COLOR 颜色
 * @property string|null $REG_TYPE 规则类型
 * @property string|null $STATUS 可用状态
 * @property int|null $SORT_CODE 排序码
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 */
class MobileResource extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'mobile_resource';
}

