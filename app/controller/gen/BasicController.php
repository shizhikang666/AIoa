<?php

declare(strict_types=1);

namespace app\controller\gen;

use app\controller\sys\BaseSysController;
use app\service\gen\BasicService;
use app\support\ApiResponse;
use app\support\DownloadResponseHeaders;
use RuntimeException;
use think\Request;
use think\Response;
use Throwable;

class BasicController extends BaseSysController
{
    public function __construct(private readonly BasicService $basicService = new BasicService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->detail($this->requiredString($request, 'id')));
    }

    public function previewGen(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->previewGen($this->requiredString($request, 'id')));
    }

    public function execGenZip(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->basicService->execGenZip($this->requiredString($request, 'id')));
    }

    public function tables(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->tables($request->get()));
    }

    public function tableColumns(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->tableColumns($this->requiredString($request, 'tableName')));
    }

    public function mobileModuleSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->mobileModuleSelector($request->get()));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->add($this->bodyInput($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->edit($this->bodyInput($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->basicService->delete($this->bodyInput($request), $this->authPayload($request)));
    }

    public function execGenPro(): Response
    {
        return $this->deferredWrite('generator project execution');
    }

    private function deferredWrite(string $operation): Response
    {
        return ApiResponse::fail('该写入操作暂未开放', 400, [
            'operation' => $operation,
        ]);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function bodyInput(Request $request): array
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
            $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            throw new RuntimeException('invalid json body', 400);
        }

        return $request->param();
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
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
        $filename = (string)($file['filename'] ?? 'gen-basic.zip');
        $content = (string)($file['content'] ?? '');
        $contentType = (string)($file['contentType'] ?? 'application/octet-stream');

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $contentType,
            'Content-Disposition' => DownloadResponseHeaders::contentDisposition($filename),
            'Content-Length' => (string)strlen($content),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
