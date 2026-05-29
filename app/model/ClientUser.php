<?php

namespace app\model;

/**
 * Client-side user model.
 *
 * Java entity: vip.xiaonuo.client.modular.user.entity.ClientUser
 * Table: client_user
 *
 * Relation notes:
 * - C-side users are separate from sys_user.
 * - C-side relation grants use client_relation.
 *
 * @property string $ID 主键
 * @property string|null $AVATAR 头像
 * @property string|null $SIGNATURE 签名
 * @property string|null $ACCOUNT 账号
 * @property string|null $PASSWORD 密码
 * @property string|null $NAME 姓名
 * @property string|null $NICKNAME 昵称
 * @property string|null $GENDER 性别
 * @property string|null $PHONE 手机
 * @property string|null $EMAIL 邮箱
 * @property string|null $USER_STATUS 用户状态
 * @property int|null $SORT_CODE 排序码
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 */
class ClientUser extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'client_user';
}

