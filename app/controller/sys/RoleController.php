<?php

declare(strict_types=1);

namespace app\controller\sys;

use app\service\auth\RoleService;
use think\Request;
use think\Response;

class RoleController extends BaseSysController
{
    public function __construct(private readonly RoleService $roleService = new RoleService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->page($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->detail($this->requiredString($request, 'id')));
    }

    public function ownResource(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->ownResource($this->requiredString($request, 'id')));
    }

    public function grantResource(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->grantResource($this->body($request)));
    }

    public function ownMobileMenu(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->ownMobileMenu($this->requiredString($request, 'id')));
    }

    public function grantMobileMenu(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->grantMobileMenu($this->body($request)));
    }

    public function ownPermission(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->ownPermission($this->requiredString($request, 'id')));
    }

    public function grantPermission(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->grantPermission($this->body($request)));
    }

    public function ownUser(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->ownUser($this->requiredString($request, 'id')));
    }

    public function grantUser(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->grantUser($this->body($request)));
    }

    public function orgTreeSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->orgTreeSelector($request->get()));
    }

    public function resourceTreeSelector(): Response
    {
        return $this->guard(fn () => $this->roleService->resourceTreeSelector());
    }

    public function mobileMenuTreeSelector(): Response
    {
        return $this->guard(fn () => $this->roleService->mobileMenuTreeSelector());
    }

    public function permissionTreeSelector(): Response
    {
        return $this->guard(fn () => $this->roleService->permissionTreeSelector());
    }

    public function roleSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->roleSelector($request->get()));
    }

    public function userSelector(Request $request): Response
    {
        return $this->guard(fn () => $this->roleService->userSelector($request->get()));
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
