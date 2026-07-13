<?php

namespace app\model;

/**
 * Business file relation model.
 *
 * Java entity: vip.xiaonuo.biz.modular.bizfilerelation.entity.BizFileRelation
 * Table: biz_file_relation
 *
 * Relation notes:
 * - OBJECT_ID is the business row id.
 * - TARGET_ID is the file id, usually dev_file.ID.
 * - CATEGORY identifies the owning business scene.
 *
 * Non-persistent Java fields include downloadPath, thumbnail, sizeKb, suffix,
 * name, createUserName, and avatar.
 *
 * @property string $ID 主键
 * @property string|null $OBJECT_ID 对象ID
 * @property string|null $TARGET_ID fileID
 * @property string|null $CATEGORY 分类
 * @property string|null $FILE_NAME 文件名称
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $EXT_JSON 扩展信息
 * @property string $TENANT_ID 租户id
 */
class BizFileRelation extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'biz_file_relation';
}

