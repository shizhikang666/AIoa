<?php

namespace app\model;

/**
 * Camunda historic variable instance model.
 *
 * Table: act_hi_varinst
 *
 * Relation notes:
 * - PROC_INST_ID_ links variables to a historic process instance.
 * - NAME_ stores variable names such as title, status, amount, org, tenantId.
 */
class ActHiVarinst extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_hi_varinst';
}
