<?php

namespace app\model;

/**
 * Camunda historic task instance model.
 *
 * Table: act_hi_taskinst
 *
 * Important query columns:
 * - ASSIGNEE_: completed task user.
 * - PROC_INST_ID_: process instance.
 * - PROC_DEF_KEY_: process key.
 * - START_TIME_ and END_TIME_: task timeline.
 */
class ActHiTaskinst extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_hi_taskinst';
}
