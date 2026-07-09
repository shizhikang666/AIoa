<?php

declare(strict_types=1);

namespace app\support;

final class WorkflowTitleFormatter
{
    private const PROCESS_LABELS = [
        'Process_ask_leave' => "\u{8bf7}\u{5047}\u{7533}\u{8bf7}",
        'Process_procure' => "\u{91c7}\u{8d2d}\u{7533}\u{8bf7}",
        'Process_procure_in_warehouse' => "\u{91c7}\u{8d2d}\u{5165}\u{5e93}",
        'Process_reimbursement' => "\u{62a5}\u{9500}\u{7533}\u{8bf7}",
        'Process_make_payment' => "\u{4ed8}\u{6b3e}\u{7533}\u{8bf7}",
        'Process_payment' => "\u{6536}\u{6b3e}\u{5355}\u{7533}\u{8bf7}",
        'Process_sale_project_init' => "\u{9879}\u{76ee}\u{7acb}\u{9879}\u{7533}\u{8bf7}",
        'Process_sale_project_delivery' => "\u{9879}\u{76ee}\u{53d1}\u{8d27}\u{7533}\u{8bf7}",
        'Process_sale_project_play' => "\u{9879}\u{6536}\u{6b3e}\u{786e}\u{8ba4}",
        'Process_project_reissue_product' => "\u{9879}\u{76ee}\u{8865}\u{8d27}\u{7533}\u{8bf7}",
        'Process_sale_project_product_return' => "\u{9879}\u{76ee}\u{9000}\u{8d27}\u{7533}\u{8bf7}",
    ];

    private const GENERAL_PROCESS_KEYS = [
        'Process_ask_leave' => true,
        'Process_procure' => true,
        'Process_procure_in_warehouse' => true,
        'Process_reimbursement' => true,
        'Process_make_payment' => true,
        'Process_payment' => true,
    ];

    public static function processLabel(string $processKey): ?string
    {
        return self::PROCESS_LABELS[$processKey] ?? null;
    }

    public static function titleForProcess(string $processKey, string $starterName): string
    {
        $label = self::processLabel($processKey) ?? "\u{6d41}\u{7a0b}\u{7533}\u{8bf7}";

        return trim($starterName) . "\u{53d1}\u{8d77}\u{7684}" . $label;
    }

    public static function displayTitle(?string $title, string $processKey, ?string $starterName = null): ?string
    {
        if (isset(self::GENERAL_PROCESS_KEYS[$processKey])) {
            $starter = self::starterName($starterName, $title);
            if ($starter !== '') {
                return self::titleForProcess($processKey, $starter);
            }
        }

        return $title;
    }

    private static function starterName(?string $starterName, ?string $title): string
    {
        $starter = trim((string)$starterName);
        if ($starter !== '') {
            return $starter;
        }

        $title = trim((string)$title);
        if ($title !== '' && preg_match('/^(.*?)\x{53d1}\x{8d77}\x{7684}/u', $title, $matches) === 1) {
            return trim((string)$matches[1]);
        }

        return '';
    }
}
