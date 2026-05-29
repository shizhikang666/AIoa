<?php

namespace app\model;

/**
 * Camunda historic process instance model.
 *
 * Table: act_hi_procinst
 *
 * Important query columns:
 * - PROC_INST_ID_: process instance id.
 * - PROC_DEF_KEY_: process key.
 * - START_USER_ID_: process starter.
 * - START_TIME_ and END_TIME_: process timeline.
 */
class ActHiProcinst extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_hi_procinst';
}
