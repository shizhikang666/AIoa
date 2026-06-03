<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizDataReportService;
use think\Request;
use think\Response;

class BizDataReportController extends BaseSysController
{
    public function __construct(private readonly BizDataReportService $reportService = new BizDataReportService())
    {
    }

    public function saleProjectListDetails(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->saleProjectListDetails($request->post(), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }
}
