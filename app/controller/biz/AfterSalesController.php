<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\AfterSalesService;
use think\Request;
use think\Response;

class AfterSalesController extends BaseSysController
{
    public function __construct(private readonly AfterSalesService $service = new AfterSalesService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->service->page($request->get(), $this->payload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->service->detail($this->requiredString($request, 'id'), $this->payload($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->service->add($this->body($request), $this->payload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->service->edit($this->body($request), $this->payload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->service->delete($this->body($request), $this->payload($request)));
    }

    public function categoryList(Request $request): Response
    {
        $includeDisabled = filter_var($request->get('includeDisabled', false), FILTER_VALIDATE_BOOLEAN);

        return $this->guard(fn () => $this->service->categoryList($this->payload($request), $includeDisabled));
    }

    public function categoryAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->service->categoryAdd($this->body($request), $this->payload($request)));
    }

    public function categoryEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->service->categoryEdit($this->body($request), $this->payload($request)));
    }

    public function categoryDelete(Request $request): Response
    {
        return $this->guard(fn () => $this->service->categoryDelete($this->body($request), $this->payload($request)));
    }

    private function payload(Request $request): array
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
        $raw = method_exists($request, 'getContent') ? trim((string)$request->getContent()) : '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->param();
    }
}
