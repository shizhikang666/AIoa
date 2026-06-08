<?php

declare(strict_types=1);

namespace app\controller\gen;

use app\controller\sys\BaseSysController;
use app\service\gen\ConfigService;
use RuntimeException;
use think\Request;
use think\Response;

class ConfigController extends BaseSysController
{
    public function __construct(private readonly ConfigService $configService = new ConfigService())
    {
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->list($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->detail($this->requiredString($request, 'id')));
    }

    public function editBatch(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->editBatch($this->bodyInput($request), $this->authPayload($request)));
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
}
