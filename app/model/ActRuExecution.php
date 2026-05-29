<?php

namespace app\model;

/**
 * Camunda runtime execution model.
 *
 * Table: act_ru_execution
 *
 * Relation notes:
 * - PROC_INST_ID_ groups runtime executions for one process instance.
 * - PROC_DEF_ID_ points to act_re_procdef.ID_.
 */
class ActRuExecution extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_ru_execution';
}
