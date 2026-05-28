<?php

namespace app\model;

use think\Model;

/**
 * Base model for Java OA compatibility tables.
 *
 * Column names intentionally keep the original SQL spelling, especially upper-case
 * field names, so later agents can map Java Mapper SQL without lossy renaming.
 */
abstract class BaseModel extends Model
{
    /**
     * Java OA tables store audit fields explicitly. Do not let ThinkPHP write
     * automatic timestamps until a migration policy is defined.
     *
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * Default primary key for most OA tables.
     *
     * @var string
     */
    protected $pk = 'ID';
}

