<?php

namespace app\model;

/**
 * System user model.
 *
 * Java entity: vip.xiaonuo.sys.modular.user.entity.SysUser
 * Table: sys_user
 *
 * Relation notes:
 * - ORG_ID points to sys_org.ID.
 * - POSITION_ID points to sys_position.ID.
 * - DIRECTOR_ID points to sys_user.ID.
 * - TENANT_ID points to tenants.Tenant_ID.
 * - Roles, direct resources, and permissions are stored in sys_relation.
 *
 * Sensitive Java fields using SM4 type handlers: ID_CARD_NUMBER, PHONE,
 * EMERGENCY_PHONE.
 *
 * @property string $ID 主键
 * @property string|null $AVATAR 头像
 * @property string|null $SIGNATURE 签名
 * @property string|null $ACCOUNT 账号
 * @property string|null $PASSWORD 密码
 * @property string|null $NAME 姓名
 * @property string|null $NICKNAME 昵称
 * @property string|null $GENDER 性别
 * @property string|null $AGE 年龄
 * @property string|null $BIRTHDAY 出生日期
 * @property string|null $NATION 民族
 * @property string|null $NATIVE_PLACE 籍贯
 * @property string|null $HOME_ADDRESS 家庭住址
 * @property string|null $MAILING_ADDRESS 通信地址
 * @property string|null $ID_CARD_TYPE 证件类型
 * @property string|null $ID_CARD_NUMBER 证件号码
 * @property string|null $PHONE 手机
 * @property string|null $EMAIL 邮箱
 * @property string|null $ORG_ID 机构id
 * @property string|null $POSITION_ID 职位id
 * @property string|null $DIRECTOR_ID 主管id
 * @property string|null $POSITION_JSON 兼任信息
 * @property string|null $USER_STATUS 用户状态
 * @property int|null $SORT_CODE 排序码
 * @property string|null $EXT_JSON 扩展信息
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 * @property string $TENANT_ID 租户id
 */
class SysUser extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'sys_user';
}

