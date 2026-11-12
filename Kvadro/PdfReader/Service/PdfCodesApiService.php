<?php

declare(strict_types=1);

namespace Kvadro\PdfReader\Service;

use Psr\Log\LoggerInterface;
use Kvadro\PdfReader\Model\Config;

class PdfCodesApiService
{
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function validate($path, $baseUrl, $token): bool
    {
        if (!is_readable($path)) {
            $this->logger->info('Bar Codes API: pdf file not readable', ['path' => $path]);
            return false;
        }
        if ($baseUrl === '' || $token === '') {
            $this->logger->info('Bar Codes API: base URL or bearer token is not configured');
            return false;
        }
        return true;
    }
    private function apiRequest($path, $apiUrl, $token): array
    {
        $mime = $this->guessMimeType($path);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => [
                'file' => new \CURLFile($path, $mime, basename($path)),
            ],
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token
            ],
        ]);
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return [$response, $httpCode, $curlError];
    }
    public function loadZipByPdf(string $path): ?array
    {
        $token = $this->config->getReaderPdfApiBearerToken();
        $baseUrl = $this->config->getReaderPdfApiBaseUrl();

        if (!$this->validate($path, $baseUrl, $token)) {
            return null;
        }
        $apiUrl = $baseUrl . 'api/pdf/split';
        list($response, $httpCode, $curlError) = $this->apiRequest($path, $apiUrl, $token);
        if ($response === false || $httpCode !== 200) {
            $this->logger->info('loadZipByPdf API curl error: ' . $curlError, ['path' => $path]);
            return null;
        }
        $results = json_decode($response, true);
        if (!is_array($results) || empty($results['download_url']) ) {
            return null;
        }
        return $results;
    }

    /**
     * @return string[]|null
     */
    public function loadCodesFromFile(string $path, $type = 'image'): ?array
    {
        $token = $this->config->getReaderPdfApiBearerToken();
        $baseUrl = $this->config->getReaderPdfApiBaseUrl();

        if (!$this->validate($path, $baseUrl, $token)) {
            return null;
        }

        $apiUrl = $baseUrl . 'api/'.$type.'/codes';

        list($response, $httpCode, $curlError) = $this->apiRequest($path, $apiUrl, $token);

        if ($response === false) {
            $this->logger->info('Bar Codes API curl error: ' . $curlError, ['path' => $path]);
            return null;
        }

        $codes = $this->parseResponse($response);
        $this->logger->info('Bar Codes API result', [
            'path' => $path,
            'http_code' => $httpCode,
            'code' => $codes ? $codes[0] : null,
        ]);

        return $codes;
    }

    private function guessMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    /**
     * @return string[]|null
     */
    private function parseResponse(string $response): ?array
    {
        $results = json_decode($response, true);
        $this->logger->info('Bar Codes API result', $results);
        if (!is_array($results) || empty($results['codes']) || !is_array($results['codes'])) {
            return null;
        }

        $codes = [];

        foreach ($results['codes'] as $code) {
            $codeType = $code['type'] ?? '';
            if (!empty($code['data']) && in_array($codeType, ['I2/5', 'CODE-128','QR-Code'])) {
                $codes[] = (string)$code['data'];
            }
        }

        return $codes ?: null;
    }
}
