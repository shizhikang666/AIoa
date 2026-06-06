<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\ProductService;
use think\Request;
use think\Response;

class ProductController extends BaseSysController
{
    public function __construct(private readonly ProductService $productService = new ProductService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->productService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->productService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->productService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function children(Request $request): Response
    {
        return $this->guard(fn () => $this->productService->children($this->childrenInput($request)));
    }

    public function editStatus(Request $request): Response
    {
        return $this->guard(fn () => $this->productService->editStatus($this->body($request), $this->authPayload($request)));
    }

    public function editReconciliation(Request $request): Response
    {
        return $this->guard(fn () => $this->productService->editReconciliation($this->body($request), $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    private function childrenInput(Request $request): array
    {
        $input = $request->post();
        if ($input !== []) {
            return $input;
        }

        $idList = $request->param('idList', $request->param('ids', []));
        if (is_array($idList)) {
            return $idList;
        }

        if (is_string($idList) && trim($idList) !== '') {
            return ['idList' => $idList];
        }

        return [];
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
