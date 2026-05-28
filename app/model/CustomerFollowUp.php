<?php

namespace app\model;

/**
 * Customer follow-up model.
 *
 * Java entity: vip.xiaonuo.biz.modular.followup.entity.CustomerFollowUp
 * Table: customer_follow_up
 *
 * Relation notes:
 * - CUSTOMER_ID points to customer.ID.
 * - CREATE_USER points to sys_user.ID through audit columns.
 *
 * @property string $ID
 * @property string $CUSTOMER_ID
 * @property string $FOLLOW_UP_TIME
 * @property string $CONTENT
 * @property string|null $EXT_JSON
 * @property string $TENANT_ID
 */
class CustomerFollowUp extends BaseModel
{
    /**
     * @var string
     */
    protected $name = 'customer_follow_up';
}
