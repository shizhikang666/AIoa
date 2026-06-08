<?php

declare(strict_types=1);

namespace app\controller\dev;

use app\controller\sys\BaseSysController;
use app\service\dev\EmailService;
use RuntimeException;
use think\Request;
use think\Response;

class EmailController extends BaseSysController
{
    public function __construct(private readonly EmailService $emailService = new EmailService())
    {
    }

    public function page(Request $request): Response
    {
        return $this->guard(fn () => $this->emailService->page($request->get(), $this->tenantId($request)));
    }

    public function detail(Request $request): Response
    {
        return $this->guard(fn () => $this->emailService->detail($this->requiredString($request, 'id'), $this->tenantId($request)));
    }

    public function delete(Request $request): Response
    {
        return $this->guard(fn () => $this->emailService->delete($this->deleteIds($this->bodyInput($request)), $this->payload($request)));
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
