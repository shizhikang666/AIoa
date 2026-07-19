<?php

declare(strict_types=1);

namespace app\command;

use think\console\command\RouteList;
use think\console\Input;
use think\console\Output;

/**
 * Render the framework route table without writing runtime/route_list.php.
 *
 * Deployment candidates intentionally keep their runtime parent read-only;
 * route discovery must therefore remain a pure readiness check.
 */
class ReadOnlyRouteList extends RouteList
{
    protected function execute(Input $input, Output $output)
    {
        $this->getRouteList();

        return 0;
    }
}
