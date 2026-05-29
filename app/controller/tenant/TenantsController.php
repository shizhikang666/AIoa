<?php

declare(strict_types=1);

namespace app\controller\tenant;

use app\controller\sys\BaseSysController;
use app\service\tenant\TenantsService;
use think\Request;
use think\Response;

class TenantsController extends BaseSysController
{
    public function __construct(private readonly TenantsService $tenantsService = new TenantsService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->tenantsService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->tenantsService->detail($this->tenantId($request)));
    }

    private function tenantId(Request $request): string
    {
        $value = trim((string)$request->param('tenantId', $request->param('id', '')));
        if ($value === '') {
            return $this->requiredString($request, 'id');
        }

        return $value;
    }
}
