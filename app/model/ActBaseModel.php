<?php

namespace app\model;

/**
 * Base model for Camunda-style workflow engine tables.
 *
 * The `act_*` tables keep Camunda column spelling, including underscore suffixes.
 * Models are passive compatibility wrappers; workflow behavior belongs to
 * workflow-agent services.
 */
abstract class ActBaseModel extends BaseModel
{
    /**
     * @var string
     */
    protected $pk = 'ID_';
}
