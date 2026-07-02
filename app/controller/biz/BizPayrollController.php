<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\BizPayrollService;
use app\support\ApiResponse;
use RuntimeException;
use think\Request;
use think\Response;
use Throwable;

class BizPayrollController extends BaseSysController
{
    public function __construct(private readonly BizPayrollService $payrollService = new BizPayrollService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->page($request->get(), $this->authPayload($request)));
    }

    public function myPage(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->myPage($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function downloadImportTemplate(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->payrollService->downloadImportTemplate());
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->add($this->body($request), $this->authPayload($request)));
    }

    public function importExcel(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->importExcel(
            $request->file('file'),
            $this->body($request),
            $this->authPayload($request)
        ));
    }

    public function export(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->payrollService->export(
            $request->get(),
            $this->authPayload($request)
        ));
    }

    public function generateAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->generate($this->body($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->edit($this->body($request), $this->authPayload($request)));
    }

    public function bathEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->bathEdit($this->body($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->payrollService->delete($this->body($request), $this->authPayload($request)));
    }

    private function deferredWrite(string $operation): Response
    {
        return ApiResponse::fail('该写入操作暂未开放', 400, [
            'operation' => $operation,
        ]);
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

    private function downloadGuard(callable $callback): Response
    {
        try {
            return $this->downloadResponse($callback());
        } catch (RuntimeException $exception) {
            $code = $exception->getCode();
            $status = is_int($code) && $code >= 400 && $code <= 599 ? $code : 400;

            return ApiResponse::fail($exception->getMessage(), $status);
        } catch (Throwable) {
            return ApiResponse::fail('服务器错误', 500);
        }
    }

    /**
     * @param array{filename:string, contentType:string, content:string} $file
     */
    private function downloadResponse(array $file): Response
    {
        $filename = (string)($file['filename'] ?? 'download.xlsx');
        $content = (string)($file['content'] ?? '');
        $contentType = (string)($file['contentType'] ?? 'application/octet-stream');
        $encodedFilename = rawurlencode($filename);

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . $encodedFilename,
            'Content-Length' => (string)strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
