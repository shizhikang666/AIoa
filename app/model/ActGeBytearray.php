<?php

namespace app\model;

/**
 * Camunda byte array/resource model.
 *
 * Table: act_ge_bytearray
 *
 * Relation notes:
 * - DEPLOYMENT_ID_ points to act_re_deployment.ID_.
 * - BYTEARRAY_ID_ from variable tables can point here for serialized values.
 */
class ActGeBytearray extends ActBaseModel
{
    /**
     * @var string
     */
    protected $name = 'act_ge_bytearray';
}
