<?php

namespace app\model;

/**
 * Camunda historic identity link model.
 *
 * Table: act_hi_identitylink
 *
 * Relation notes:
 * - TASK_ID_ links identity data to historic tasks.
 * - PROC_INST_ID_ links identity data to process instances.
 */
class ActHiIdentitylink extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_hi_identitylink';
}
