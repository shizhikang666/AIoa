<?php

namespace app\model;

/**
 * Camunda historic activity instance model.
 *
 * Table: act_hi_actinst
 *
 * Important query columns:
 * - PROC_INST_ID_: process instance.
 * - ACT_ID_ and ACT_NAME_: activity node.
 * - ASSIGNEE_: participant when the activity is a user task.
 */
class ActHiActinst extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_hi_actinst';
}
