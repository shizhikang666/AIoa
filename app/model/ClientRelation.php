<?php

namespace app\model;

/**
 * Client-side relation model.
 *
 * Java entity: vip.xiaonuo.client.modular.relation.entity.ClientRelation
 * Table: client_relation
 *
 * Relation notes:
 * - OBJECT_ID, TARGET_ID, and CATEGORY are interpreted by C-side business logic.
 *
 * @property string $ID 主键
 * @property string|null $OBJECT_ID 对象ID
 * @property string|null $TARGET_ID 目标ID
 * @property string|null $CATEGORY 分类
 * @property string|null $EXT_JSON 扩展信息
 */
class ClientRelation extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'client_relation';
}

