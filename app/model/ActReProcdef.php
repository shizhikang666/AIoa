<?php

namespace app\model;

/**
 * Camunda process definition model.
 *
 * Table: act_re_procdef
 *
 * Important columns:
 * - KEY_: process key, such as Process_ask_leave.
 * - VERSION_: deployed version.
 * - DEPLOYMENT_ID_: deployment relation.
 */
class ActReProcdef extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_re_procdef';
}
