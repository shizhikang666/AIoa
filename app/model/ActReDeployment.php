<?php

namespace app\model;

/**
 * Camunda deployment model.
 *
 * Table: act_re_deployment
 *
 * Relation notes:
 * - act_re_procdef.DEPLOYMENT_ID_ points to this table.
 * - act_ge_bytearray.DEPLOYMENT_ID_ stores BPMN resources for a deployment.
 */
class ActReDeployment extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_re_deployment';
}
