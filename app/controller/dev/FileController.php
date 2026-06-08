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

    public function uploadDynamicReturnId(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnId(null, $request->file('file'), $this->payload($request)));
    }

    public function uploadDynamicReturnUrl(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnUrl(null, $request->file('file'), $this->payload($request)));
    }

    public function uploadDynamicReturnFile(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnFile(null, $request->file('file'), $this->payload($request)));
    }

    public function uploadLocalReturnId(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnId('LOCAL', $request->file('file'), $this->payload($request)));
    }

    public function uploadLocalReturnUrl(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnUrl('LOCAL', $request->file('file'), $this->payload($request)));
    }

    public function uploadLocalReturnFile(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnFile('LOCAL', $request->file('file'), $this->payload($request)));
    }

    public function uploadAliyunReturnId(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnId('ALIYUN', $request->file('file'), $this->payload($request)));
    }

    public function uploadAliyunReturnUrl(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnUrl('ALIYUN', $request->file('file'), $this->payload($request)));
    }

    public function uploadAliyunReturnFile(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnFile('ALIYUN', $request->file('file'), $this->payload($request)));
    }

    public function uploadTencentReturnId(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnId('TENCENT', $request->file('file'), $this->payload($request)));
    }

    public function uploadTencentReturnUrl(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnUrl('TENCENT', $request->file('file'), $this->payload($request)));
    }

    public function uploadTencentReturnFile(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnFile('TENCENT', $request->file('file'), $this->payload($request)));
    }

    public function uploadMinioReturnId(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnId('MINIO', $request->file('file'), $this->payload($request)));
    }

    public function uploadMinioReturnUrl(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnUrl('MINIO', $request->file('file'), $this->payload($request)));
    }

    public function uploadMinioReturnFile(Request $request): Response
    {
        return $this->guard(fn () => $this->fileService->uploadReturnFile('MINIO', $request->file('file'), $this->payload($request)));
    }

    private function tenantId(Request $request): ?string
    {
        $payload = $this->payload($request);
        if (!is_array($payload)) {
            return null;
        }

        $tenantId = (string)($payload['tenant_id'] ?? $payload['tenantId'] ?? '');

        return $tenantId === '' ? null : $tenantId;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
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
