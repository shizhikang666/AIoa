<?php

declare(strict_types=1);

namespace app\controller\biz;

use app\controller\sys\BaseSysController;
use app\service\biz\FileRelationService;
use think\Request;
use think\Response;

class FileRelationController extends BaseSysController
{
    public function __construct(private readonly FileRelationService $fileRelationService = new FileRelationService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->page($request->get(), $this->authPayload($request)));
    }

    public function list(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->list($request->get(), $this->authPayload($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->detail($this->requiredString($request, 'id'), $this->authPayload($request)));
    }

    public function add(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->add($this->bodyInput($request), $this->authPayload($request)));
    }

    public function edit(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->edit($this->bodyInput($request), $this->authPayload($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->delete($this->deleteIds($this->bodyInput($request)), $this->authPayload($request)));
    }

    public function projectCaseDelete(Request $request): Response
    {
        return $this->guard(fn () => $this->fileRelationService->delete([$this->requiredString($request, 'id')], $this->authPayload($request)));
    }

    private function authPayload(Request $request): array
    {
        $payload = $request->middleware('auth_payload', []);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string|int, mixed>
     */
    private function bodyInput(Request $request): array
    {
        $input = $request->post();
        $decodedInput = $this->decodeJsonLikeInput($input);
        if ($decodedInput !== null) {
            return $decodedInput;
        }
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

        $params = $request->param();

        return $input === [] ? $params : array_merge($params, $input);
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<string|int, mixed>|null
     */
    private function decodeJsonLikeInput(array $input): ?array
    {
        foreach ($input as $key => $value) {
            foreach ([$key, $value] as $candidate) {
                if (!is_string($candidate)) {
                    continue;
                }
                $candidate = trim($candidate);
                if ($candidate === '' || !in_array($candidate[0], ['{', '['], true)) {
                    continue;
                }
                $decoded = json_decode($candidate, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $input
     * @return array<int, string>
     */
    private function deleteIds(array $input): array
    {
        if (isset($input[0])) {
            return array_values(array_filter(array_map(static function (mixed $item): string {
                if (is_array($item)) {
                    return trim((string)($item['id'] ?? $item['ID'] ?? ''));
                }

                return trim((string)$item);
            }, $input)));
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

                return array_values(array_filter(array_map(static fn (mixed $id): string => trim((string)$id), $value)));
            }
        }

        return [];
    }
}
