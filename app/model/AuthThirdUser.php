<?php

namespace app\model;

/**
 * Third-party auth user binding model.
 *
 * Java entity: vip.xiaonuo.auth.modular.third.entity.AuthThirdUser
 * Table: auth_third_user
 *
 * Relation notes:
 * - USER_ID points to sys_user.ID.
 *
 * @property string $ID 主键
 * @property string|null $THIRD_ID 三方用户id
 * @property string|null $USER_ID 系统用户id
 * @property string|null $AVATAR 头像
 * @property string|null $NAME 姓名
 * @property string|null $NICKNAME 昵称
 * @property string|null $GENDER 性别
 * @property string|null $CATEGORY 分类
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 */
class AuthThirdUser extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'auth_third_user';
}

