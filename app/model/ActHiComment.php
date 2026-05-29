<?php

namespace app\model;

/**
 * Camunda historic comment model.
 *
 * Table: act_hi_comment
 *
 * Relation notes:
 * - TASK_ID_ links comments to tasks.
 * - PROC_INST_ID_ links comments to process instances.
 */
class ActHiComment extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_hi_comment';
}
