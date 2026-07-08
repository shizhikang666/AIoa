<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\user\OrgService;
use app\service\user\PositionService;
use app\service\user\UserDirectoryService;
use app\service\user\UserOrgMigrationService;
use app\support\ApiResponse;
use app\support\DownloadResponseHeaders;
use RuntimeException;
use Throwable;
use think\Request;
use think\Response;

class UserController extends BaseSysController
{
    public function __construct(
        private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService(),
        private readonly OrgService $orgService = new OrgService(),
        private readonly PositionService $positionService = new PositionService(),
        private readonly UserOrgMigrationService $userOrgMigrationService = new UserOrgMigrationService()
    ) {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->detail($this->requiredString($request, 'id')));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->addUser(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->editUser(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function listDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->listDetail($request->get()));
    }

    public function downloadImportUserTemplate(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->userDirectoryService->downloadImportUserTemplate(
            $request->middleware('auth_payload', [])
        ));
    }

    public function import(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->importUsers(
            $request->file('file'),
            $request->middleware('auth_payload', [])
        ));
    }

    public function export(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->userDirectoryService->exportUsers(
            $request->get(),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function exportUserInfo(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->userDirectoryService->exportUserInfoFile(
            $this->requiredString($request, 'id'),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function bizExport(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->userDirectoryService->exportUsers(
            $request->get(),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizExportUserInfo(Request $request): Response
    {
        return $this->downloadGuard(fn () => $this->userDirectoryService->exportUserInfoFile(
            $this->requiredString($request, 'id'),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function disableUser(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->setUserStatus(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            'DISABLED',
            false
        ));
    }

    public function enableUser(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->setUserStatus(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            'ENABLE',
            false
        ));
    }

    public function resetPassword(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->resetPassword(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->userDirectoryService->deleteUsers(
                $this->deleteIds($input),
                $request->middleware('auth_payload', []),
                false
            );
        });
    }

    public function bizDisableUser(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->setUserStatus(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            'DISABLED',
            true
        ));
    }

    public function bizEnableUser(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->setUserStatus(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            'ENABLE',
            true
        ));
    }

    public function bizResetPassword(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->resetPassword(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizDelete(Request $request): Response
    {
        return $this->guard(function () use ($request): array {
            $input = $this->bodyInput($request);

            return $this->userDirectoryService->deleteUsers(
                $this->deleteIds($input),
                $request->middleware('auth_payload', []),
                true
            );
        });
    }

    public function bizAdd(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->addUser(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function bizEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->editUser(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function ownRole(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->ownRole($this->requiredString($request, 'id')));
    }

    public function grantRole(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantRole(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            false
        ));
    }

    public function grantResource(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantResource(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function grantPermission(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantPermission(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function bizGrantRole(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->grantRole(
            $this->bodyInput($request),
            $request->middleware('auth_payload', []),
            true
        ));
    }

    public function ownResource(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->ownResource($this->requiredString($request, 'id')));
    }

    public function ownPermission(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->ownPermission($this->requiredString($request, 'id')));
    }

    public function orgTreeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->orgService->selector($request->get()));
    }

    public function positionSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->positionService->selector($request->get()));
    }

    public function roleSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->roleSelector($request->get()));
    }

    public function userSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->userSelector($request->get()));
    }

    public function migrationPreview(Request $request): Response
    {
        return $this->guard(fn () => $this->userOrgMigrationService->preview(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function migrationExecute(Request $request): Response
    {
        return $this->guard(fn () => $this->userOrgMigrationService->execute(
            $this->bodyInput($request),
            $request->middleware('auth_payload', [])
        ));
    }

    public function migrationLogPage(Request $request): Response
    {
        return $this->guard(fn () => $this->userOrgMigrationService->logPage(
            $request->get(),
            $request->middleware('auth_payload', [])
        ));
    }

    public function migrationLogDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->userOrgMigrationService->logDetail(
            $this->requiredString($request, 'id'),
            $request->middleware('auth_payload', [])
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyInput(Request $request): array
    {
        $input = $request->post();
        if ($input === []) {
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
        }

        return $input === [] ? $request->param() : $input;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, mixed>
     */
    private function deleteIds(array $input): array
    {
        if (isset($input[0])) {
            return $input;
        }

        foreach (['idList', 'ids', 'id', 'userIds'] as $key) {
            if (array_key_exists($key, $input)) {
                return is_array($input[$key]) ? $input[$key] : [(string)$input[$key]];
            }
        }

        return [];
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
        $filename = (string)($file['filename'] ?? 'download.txt');
        $content = (string)($file['content'] ?? '');
        $contentType = (string)($file['contentType'] ?? 'application/octet-stream');

        $textual = str_starts_with($contentType, 'text/')
            || str_contains($contentType, 'json')
            || str_contains($contentType, 'xml')
            || str_contains($contentType, 'csv');

        return Response::create($content, 'html', 200)->header([
            'Content-Type' => $textual ? $contentType . '; charset=utf-8' : $contentType,
            'Content-Disposition' => DownloadResponseHeaders::contentDisposition($filename),
            'Content-Length' => (string)strlen($content),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
