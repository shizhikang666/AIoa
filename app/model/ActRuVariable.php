<?php

namespace app\model;

/**
 * Camunda runtime variable model.
 *
 * Table: act_ru_variable
 *
 * Relation notes:
 * - PROC_INST_ID_ links variables to a process instance.
 * - TASK_ID_ links task-local variables.
 * - BYTEARRAY_ID_ can point to act_ge_bytearray.ID_.
 */
class ActRuVariable extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_ru_variable';
}
