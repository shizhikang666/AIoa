<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\ConfigService;
use RuntimeException;
use think\Request;
use think\Response;

class ConfigController extends BaseSysController
{
    public function __construct(private readonly ConfigService $configService = new ConfigService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->page($request->get()));
    }

    public function sysBaseList(): Response
    {
        return $this->guard(fn () => $this->configService->sysBaseList());
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->list($request->get()));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->detail($this->requiredString($request, 'id')));
    }

    public function add(Request $request): Response
    {
        return $this->guard(function () use ($request): ?array {
            $this->configService->add($this->bodyInput($request), $this->authPayload($request));

            return null;
        });
    }

    public function edit(Request $request): Response
    {
        return $this->guard(function () use ($request): ?array {
            $this->configService->edit($this->bodyInput($request), $this->authPayload($request));

            return null;
        });
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->configService->delete($this->deleteIds($this->bodyInput($request)), $this->authPayload($request)));
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

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        if (isset($input[0])) {
            return array_values(array_map(static function (mixed $item): string {
                if (is_array($item)) {
                    if (!array_key_exists('id', $item) && !array_key_exists('ID', $item)) {
                        throw new RuntimeException('missing id', 400);
                    }

                    $id = trim((string)($item['id'] ?? $item['ID'] ?? ''));
                    if ($id === '') {
                        throw new RuntimeException('missing id', 400);
                    }

                    return $id;
                }

                $id = trim((string)$item);
                if ($id === '') {
                    throw new RuntimeException('missing id', 400);
                }

                return $id;
            }, $input));
        }

        foreach (['idList', 'ids', 'id'] as $key) {
            if (array_key_exists($key, $input)) {
                $value = $input[$key];
                if (is_string($value)) {
                    $value = explode(',', $value);
                }
                if (!is_array($value)) {
                    $value = [$value];
                }

                return array_values(array_map(static function (mixed $id): string {
                    $id = trim((string)$id);
                    if ($id === '') {
                        throw new RuntimeException('missing id', 400);
                    }

                    return $id;
                }, $value));
            }
        }

        return [];
    }
}
