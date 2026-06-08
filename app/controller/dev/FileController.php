<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\FileService;
use app\support\ApiResponse;
use RuntimeException;
use Throwable;
use think\Request;
use think\Response;

class FileController extends BaseSysController
{
    public function __construct(private readonly FileService $fileService = new FileService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->page($request->get(), $this->tenantId($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->list($request->get(), $this->tenantId($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->detail($this->requiredString($request, 'id')));
    }

    public function download(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->fileService->download($this->requiredString($request, 'id')));
    }

    private function tenantId(Request $request): ?string
    {
        $payload = $request->middleware('auth_payload', []);
        if (!is_array($payload)) {
            return null;
        }

        $tenantId = (string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '');

        return $tenantId === '' ? null : $tenantId;
    }

    private function downloadGuard(callable $callback): Response
    {
        try {
            return $this->downloadResponse($callback());
        } catch (RuntimeException $exception) {
            return ApiResponse::fail($exception->getMessage(), 500);
        } catch (Throwable) {
            return ApiResponse::fail('server error', 500);
        }
    }

    /**
     * @param array{filename:string, content:string, contentType?:string} $file
     */
    private function downloadResponse(array $file): Response
    {
        $filename = (string)($file['filename'] ?? 'download');
        $content = (string)($file['content'] ?? '');
        $contentType = (string)($file['contentType'] ?? 'application/octet-stream;charset=UTF-8');
        $encodedFilename = rawurlencode($filename);

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment;filename=' . $encodedFilename,
            'Content-Length' => (string)strlen($content),
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }
}
