<?php

namespace app\model;

/**
 * Camunda runtime identity link model.
 *
 * Table: act_ru_identitylink
 *
 * Relation notes:
 * - TASK_ID_ links candidate/assignee data to runtime tasks.
 * - PROC_INST_ID_ links identity data to process instances.
 */
class ActRuIdentitylink extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_ru_identitylink';
}
