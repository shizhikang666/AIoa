#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\biz\CustomerService;
use app\service\user\UserDirectoryService;
use app\support\LegacySm4Cipher;
use app\support\SensitiveFieldCodec;
use app\support\Sm4;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
if ($loader instanceof Composer\Autoload\ClassLoader) {
    // Shared worktree vendor directories can contain an absolute app path.
    $loader->setPsr4('app\\', [dirname(__DIR__) . '/app']);
}

$checks = 0;

function smokeAssert(bool $condition, string $label): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function smokeThrows(callable $callback, string $expectedClass, string $label): void
{
    global $checks;
    $checks++;

    try {
        $callback();
    } catch (Throwable $exception) {
        if ($exception instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException($label . ' threw an unexpected exception type');
    }

    throw new RuntimeException($label . ' did not reject invalid input');
}

// Synthetic non-production block vector independently generated with OpenSSL SM4.
$standardKey = hex2bin('00112233445566778899aabbccddeeff');
$standardPlaintext = hex2bin('ffeeddccbbaa99887766554433221100');
$standardCiphertext = 'ce558c7f9e4bf964a3099fcde3a12fc4';
smokeAssert(is_string($standardKey) && is_string($standardPlaintext), 'standard vector setup');
smokeAssert(
    bin2hex(Sm4::encryptBlock($standardKey, $standardPlaintext)) === $standardCiphertext,
    'independent SM4 encryption vector'
);
smokeAssert(
    Sm4::decryptBlock($standardKey, hex2bin($standardCiphertext)) === $standardPlaintext,
    'independent SM4 decryption vector'
);

$testCipher = new LegacySm4Cipher('00112233445566778899aabbccddeeff');
$unicodePlaintext = "SM4-ECB/PKCS7 \u{517C}\u{5BB9}\u{6D4B}\u{8BD5}";
$encrypted = $testCipher->encrypt($unicodePlaintext);
smokeAssert(is_string($encrypted) && LegacySm4Cipher::looksLikeCiphertext($encrypted), 'ECB ciphertext shape');
smokeAssert($testCipher->decrypt($encrypted) === $unicodePlaintext, 'ECB PKCS#7 unicode roundtrip');
smokeAssert($testCipher->encrypt($unicodePlaintext) === $encrypted, 'deterministic encryption');
smokeAssert(
    $testCipher->encrypt('13800138000') === '5764dc88aa888fb42eac89a80febf64a',
    'deployed Java sm-crypto bridge compatibility'
);
smokeAssert($testCipher->encrypt(null) === null && $testCipher->decrypt(null) === null, 'null preservation');
smokeAssert($testCipher->encrypt('') === '' && $testCipher->decrypt('') === '', 'empty preservation');

$wrongKeyCipher = new LegacySm4Cipher('10112233445566778899aabbccddeeff');
smokeThrows(fn () => $wrongKeyCipher->decrypt($encrypted), UnexpectedValueException::class, 'wrong key');
smokeThrows(fn () => $testCipher->decrypt('not-legacy-ciphertext'), UnexpectedValueException::class, 'malformed ciphertext');
smokeThrows(fn () => $testCipher->encrypt((string)$encrypted), UnexpectedValueException::class, 'double encryption guard');

$firstLayer = (string)$testCipher->encrypt('13800138000');
$nestedPadding = 16 - (strlen($firstLayer) % 16);
$nestedPadded = $firstLayer . str_repeat(chr($nestedPadding), $nestedPadding);
$nestedCiphertext = '';
for ($offset = 0; $offset < strlen($nestedPadded); $offset += 16) {
    $nestedCiphertext .= Sm4::encryptBlock(
        hex2bin('00112233445566778899aabbccddeeff'),
        substr($nestedPadded, $offset, 16)
    );
}
smokeThrows(
    fn () => $testCipher->decrypt(bin2hex($nestedCiphertext)),
    UnexpectedValueException::class,
    'nested ciphertext refusal'
);

smokeThrows(fn () => (new LegacySm4Cipher(''))->encrypt('configured-value-required'), RuntimeException::class, 'missing runtime configuration');
smokeThrows(
    fn () => (new LegacySm4Cipher('not-hex'))->encrypt('configured-value-required'),
    RuntimeException::class,
    'invalid key configuration'
);

$codec = new SensitiveFieldCodec($testCipher);
smokeAssert(
    $codec->fieldsFor('sys_user') === ['ID_CARD_NUMBER', 'PHONE', 'EMERGENCY_PHONE'],
    'sys_user sensitive field map'
);
smokeAssert(
    $codec->fieldsFor('client_user') === ['ID_CARD_NUMBER', 'PHONE', 'EMERGENCY_PHONE'],
    'client_user sensitive field map'
);
smokeAssert($codec->fieldsFor('customer') === ['PHONE', 'DETAILS_ADDRESS'], 'customer sensitive field map');

$userPlain = [
    'ID' => 'synthetic-user',
    'ID_CARD_NUMBER' => 'synthetic-id',
    'PHONE' => 'synthetic-phone',
    'EMERGENCY_PHONE' => 'synthetic-emergency-phone',
    'NAME' => 'synthetic-name',
];
$userStored = $codec->encodeRow('sys_user', $userPlain);
smokeAssert($userStored['NAME'] === $userPlain['NAME'], 'sys_user non-sensitive field preservation');
smokeAssert($userStored['PHONE'] === $codec->lookupValue('sys_user', 'PHONE', $userPlain['PHONE']), 'sys_user deterministic phone lookup');
smokeAssert($codec->decodeRow('sys_user', $userStored) === $userPlain, 'sys_user field roundtrip');

$clientStored = $codec->encodeRow('client_user', $userPlain);
smokeAssert(
    $clientStored['PHONE'] === $codec->lookupValue('client_user', 'PHONE', $userPlain['PHONE']),
    'client_user deterministic phone lookup'
);
smokeAssert($codec->decodeRow('client_user', $clientStored) === $userPlain, 'client_user field roundtrip');

$customerPlain = [
    'ID' => 'synthetic-customer',
    'PHONE' => 'synthetic-customer-phone',
    'DETAILS_ADDRESS' => 'synthetic-customer-address',
    'NAME' => 'synthetic-customer-name',
];
$customerStored = $codec->encodeRow('customer', $customerPlain);
smokeAssert($customerStored['NAME'] === $customerPlain['NAME'], 'customer non-sensitive field preservation');
smokeAssert($codec->decodeRow('customer', $customerStored) === $customerPlain, 'customer field roundtrip');

$customerService = new CustomerService(sensitiveFields: $codec);
$applyCustomerInput = new ReflectionMethod($customerService, 'applyCustomerInput');
$customerWrite = [];
$applyCustomerInput->invokeArgs($customerService, [
    &$customerWrite,
    [
        'name' => 'synthetic-service-customer',
        'phone' => 'synthetic-service-phone',
        'detailsAddress' => 'synthetic-service-address',
    ],
    false,
]);
smokeAssert(
    $customerWrite['PHONE'] === $codec->lookupValue('customer', 'PHONE', 'synthetic-service-phone'),
    'customer service write mapping'
);
$customerRows = new ReflectionMethod($customerService, 'customerRows');
$customerResponse = $customerRows->invoke($customerService, [[
    'ID' => 'synthetic-service-customer',
    'NAME' => 'synthetic-service-customer',
    'PHONE' => $customerWrite['PHONE'],
    'DETAILS_ADDRESS' => $customerWrite['DETAILS_ADDRESS'],
]]);
smokeAssert(
    is_array($customerResponse)
        && ($customerResponse[0]['phone'] ?? null) === 'synthetic-service-phone'
        && ($customerResponse[0]['detailsAddress'] ?? null) === 'synthetic-service-address',
    'customer service response mapping'
);

$userDirectoryService = new UserDirectoryService(sensitiveFields: $codec);
$sanitizeUserRow = new ReflectionMethod($userDirectoryService, 'sanitizeUserRow');
$userResponse = $sanitizeUserRow->invoke($userDirectoryService, $userStored);
smokeAssert(
    is_array($userResponse)
        && ($userResponse['idCardNumber'] ?? null) === $userPlain['ID_CARD_NUMBER']
        && ($userResponse['phone'] ?? null) === $userPlain['PHONE']
        && ($userResponse['emergencyPhone'] ?? null) === $userPlain['EMERGENCY_PHONE'],
    'user directory response mapping'
);

$sampleCiphertext = trim((string)(getenv('OA_LEGACY_SM4_SAMPLE_CIPHER_HEX') ?: ''));
$samplePlaintextHash = trim((string)(getenv('OA_LEGACY_SM4_SAMPLE_PLAINTEXT_SHA256') ?: ''));
$samplePhoneBundle = trim((string)(getenv('OA_LEGACY_SM4_SAMPLE_PHONE_BUNDLE_BASE64') ?: ''));
$sampleState = 'skipped';
if ($sampleCiphertext !== '' || $samplePlaintextHash !== '') {
    smokeAssert($sampleCiphertext !== '' && preg_match('/\A[0-9a-f]{64}\z/iD', $samplePlaintextHash) === 1, 'live sample inputs');
    $samplePlaintext = (new LegacySm4Cipher())->decrypt($sampleCiphertext);
    smokeAssert(is_string($samplePlaintext) && hash_equals(strtolower($samplePlaintextHash), hash('sha256', $samplePlaintext)), 'live Java sample');
    $sampleState = 'verified';
}
if ($samplePhoneBundle !== '') {
    $sampleBytes = base64_decode($samplePhoneBundle, true);
    smokeAssert(is_string($sampleBytes), 'live phone sample bundle');

    $sampleCipher = new LegacySm4Cipher();
    $verifiedPhones = 0;
    foreach (explode("\n", (string)$sampleBytes) as $phoneCiphertext) {
        if ($phoneCiphertext === '') {
            continue;
        }

        try {
            $phone = $sampleCipher->decrypt($phoneCiphertext);
            if (
                is_string($phone)
                && preg_match('/\A1[3-9][0-9]{9}\z/D', $phone) === 1
                && hash_equals(strtolower($phoneCiphertext), $sampleCipher->encryptForLookup($phone))
            ) {
                $verifiedPhones++;
            }
        } catch (Throwable) {
            // A candidate from a mixed or dirty data set is not evidence of compatibility.
        }
    }

    smokeAssert($verifiedPhones > 0, 'live Java phone sample');
    $sampleState = 'verified';
}

fwrite(STDOUT, sprintf("legacy SM4 smoke passed (%d checks; live sample %s)\n", $checks, $sampleState));
