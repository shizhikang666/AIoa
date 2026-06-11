<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\auth\AuthService;
use app\service\user\UserDirectoryService;
use app\service\user\UserCenterWriteService;
use think\Request;
use think\Response;

class UserCenterController extends BaseSysController
{
    public function __construct(
        private readonly UserDirectoryService $userDirectoryService = new UserDirectoryService(),
        private readonly UserCenterWriteService $userCenterWriteService = new UserCenterWriteService(),
        private readonly AuthService $authService = new AuthService()
    ) {
    }

    public function getPicCaptcha(): Response
    {
        return $this->guard(fn () => $this->authService->getPicCaptcha());
    }

    public function loginOrgTree(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginOrgTree($this->currentUserId($request)));
    }

    public function loginPositionInfo(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginPositionInfo($this->currentUserId($request)));
    }

    public function getUserListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getUserListByIdList($this->idList($request)));
    }

    public function getOrgListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getOrgListByIdList($this->idList($request)));
    }

    public function getPositionListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getPositionListByIdList($this->idList($request)));
    }

    public function getRoleListByIdList(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getRoleListByIdList($this->idList($request)));
    }

    public function getAvatarById(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->getAvatarById($this->requiredString($request, 'id')));
    }

    public function loginWorkbench(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginWorkbench($this->currentUserId($request)));
    }

    public function loginUnreadMessagePage(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginUnreadMessagePage(
            $this->currentUserId($request),
            $request->param()
        ));
    }

    public function loginUnreadMessageDetail(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->loginUnreadMessageDetail(
            $this->currentUserId($request),
            $this->requiredString($request, 'id')
        ));
    }

    public function processConfig(Request $request): Response
    {
        return $this->guard(fn () => $this->userDirectoryService->processConfig($this->currentUserId($request)));
    }

    public function updatePassword(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->updatePassword($this->body($request), $this->authPayload($request)));
    }

    public function updateAvatar(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->updateAvatar($request->file('file'), $this->authPayload($request)));
    }

    public function updateSignature(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->updateSignature($this->body($request), $this->authPayload($request)));
    }

    public function updateUserInfo(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->updateUserInfo($this->body($request), $this->authPayload($request)));
    }

    public function centerEdit(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->updateUserInfo($this->body($request), $this->authPayload($request), true));
    }

    public function updateUserWorkbench(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->updateWorkbench($this->body($request), $this->authPayload($request)));
    }

    public function editProcessConfig(Request $request): Response
    {
        return $this->guard(fn () => $this->userCenterWriteService->editProcessConfig($this->body($request), $this->authPayload($request)));
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
