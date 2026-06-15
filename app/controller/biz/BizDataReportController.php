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
        return $this->guard(fn () => $this->reportService->saleProjectListDetails($this->body($request), $this->authPayload($request)));
    }

    public function saleProjectAmount(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->saleProjectAmount($this->body($request), $this->authPayload($request)));
    }

    public function saleProjectList(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->saleProjectList($this->body($request), $this->authPayload($request)));
    }

    public function saleProjectReport(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->saleProjectReport($this->body($request), $this->authPayload($request)));
    }

    public function saleProjectUnpaidPayment(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->saleProjectUnpaidPayment($this->body($request), $this->authPayload($request)));
    }

    public function settlementIncome(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->settlementIncome($this->body($request), $this->authPayload($request)));
    }

    public function settlementExpenses(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->settlementExpenses($this->body($request), $this->authPayload($request)));
    }

    public function saleProfit(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->saleProfit($this->body($request), $this->authPayload($request)));
    }

    public function summaryStatistics(Request $request): Response
    {
        return $this->guard(fn () => $this->reportService->summaryStatistics($this->body($request), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function body(Request $request): array
    {
        $input = $request->post();
        if ($input !== []) {
            return $input;
        }

        $raw = '';
        if (method_exists($request, 'getContent')) {
            $raw = trim((string)$request->getContent());
        }
        if ($raw === '' && method_exists($request, 'getInput')) {
            $raw = trim((string)$request->getInput());
        }
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
