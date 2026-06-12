<?php

declare(strict_types=1);

namespace app\controller\gen;

use app\controller\sys\BaseSysController;
use app\service\gen\BasicService;
use app\support\ApiResponse;
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

    private function downloadGuard(callable $callback): Response
    {
        try {
            return $this->downloadResponse($callback());
        } catch (RuntimeException $exception) {
            $code = $exception->getCode();
            $status = is_int($code) && $code >= 400 && $code <= 599 ? $code : 400;

            return ApiResponse::fail($exception->getMessage(), $status);
        } catch (Throwable) {
            return ApiResponse::fail('server error', 500);
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
        $encodedFilename = rawurlencode($filename);

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment;filename=' . $encodedFilename,
            'Content-Length' => (string)strlen($content),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
