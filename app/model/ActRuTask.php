<?php

namespace app\model;

/**
 * Camunda runtime task model.
 *
 * Table: act_ru_task
 *
 * Important query columns:
 * - ASSIGNEE_: pending task owner.
 * - PROC_INST_ID_: process instance.
 * - PROC_DEF_ID_: process definition.
 * - CREATE_TIME_: task creation time.
 */
class ActRuTask extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_ru_task';
}
