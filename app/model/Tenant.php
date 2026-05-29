<?php

namespace app\model;

/**
 * Tenant model.
 *
 * Java entity: vip.xiaonuo.tenant.modular.tenant.entity.Tenants
 * Table: tenants
 *
 * Relation notes:
 * - Tenant_ID is referenced by TENANT_ID fields on system and business tables.
 * - Mixed-case column names are preserved for compatibility.
 *
 * @property string $Tenant_ID 租户id
 * @property string $Tenant_Name 租户名称
 * @property string|null $CODE 租户编码
 * @property string|null $CREATE_TIME 创建时间
 * @property string|null $DELETE_FLAG 删除标志
 * @property string|null $CREATE_USER 创建用户
 * @property string|null $UPDATE_TIME 修改时间
 * @property string|null $UPDATE_USER 修改用户
 */
class Tenant extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'tenants';

    /**
     * @var string
     */
    protected $pk = 'Tenant_ID';
}

