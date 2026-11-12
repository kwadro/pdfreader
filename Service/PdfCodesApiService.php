<?php

declare(strict_types=1);

namespace Kvadro\PdfReader\Service;

use CURLFile;
use Exception;
use Kvadro\PdfReader\Model\Config;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\HTTP\Client\CurlFactory;
use Psr\Log\LoggerInterface;

class PdfCodesApiService
{
    private const REQUEST_TIMEOUT = 120;

    /**
     * @param Config $config
     * @param LoggerInterface $logger
     * @param CurlFactory $curlFactory
     * @param FileDriver $fileDriver
     * @param IoFile $ioFile
     */
    public function __construct(
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly CurlFactory $curlFactory,
        private readonly FileDriver $fileDriver,
        private readonly IoFile $ioFile
    ) {
    }

    /**
     * Validate that the file is readable and API credentials are configured.
     *
     * @param string $path
     * @param string $baseUrl
     * @param string $token
     * @return bool
     */
    public function validate(string $path, string $baseUrl, string $token): bool
    {
        try {
            $isReadable = $this->fileDriver->isReadable($path);
        } catch (FileSystemException) {
            $isReadable = false;
        }

        if (!$isReadable) {
            $this->logger->info('Bar Codes API: pdf file not readable', ['path' => $path]);
            return false;
        }
        if ($baseUrl === '' || $token === '') {
            $this->logger->info('Bar Codes API: base URL or bearer token is not configured');
            return false;
        }

        return true;
    }

    /**
     * Upload a file to the PDF Reader API and return response body, HTTP status and error message.
     *
     * @param string $path
     * @param string $apiUrl
     * @param string $token
     * @return array{0: string|false, 1: int, 2: string}
     */
    private function apiRequest(string $path, string $apiUrl, string $token): array
    {
        $mime = $this->guessMimeType($path);
        $pathInfo = $this->ioFile->getPathInfo($path);
        $fileName = (string)($pathInfo['basename'] ?? 'upload');

        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::REQUEST_TIMEOUT);
        $curl->addHeader('Authorization', 'Bearer ' . $token);
        $curl->setOptions([
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_POSTFIELDS => [
                'file' => new CURLFile($path, $mime, $fileName),
            ],
        ]);

        try {
            $curl->post($apiUrl, []);
        } catch (Exception $exception) {
            return [false, 0, $exception->getMessage()];
        }

        return [$curl->getBody(), (int)$curl->getStatus(), ''];
    }

    /**
     * Split a PDF via the remote API and return the response payload.
     *
     * @param string $path
     * @return array<string, mixed>|null
     */
    public function loadZipByPdf(string $path): ?array
    {
        $token = $this->config->getReaderPdfApiBearerToken();
        $baseUrl = $this->config->getReaderPdfApiBaseUrl();

        if (!$this->validate($path, $baseUrl, $token)) {
            return null;
        }

        $apiUrl = $baseUrl . 'api/pdf/split';
        [$response, $httpCode, $curlError] = $this->apiRequest($path, $apiUrl, $token);
        if ($response === false || $httpCode !== 200) {
            $this->logger->info('loadZipByPdf API curl error: ' . $curlError, ['path' => $path]);
            return null;
        }

        $results = json_decode($response, true);
        if (!is_array($results) || empty($results['download_url'])) {
            return null;
        }

        return $results;
    }

    /**
     * Read barcode / QR codes from a PDF or image file via the remote API.
     *
     * @param string $path
     * @param string $type
     * @return string[]|null
     */
    public function loadCodesFromFile(string $path, string $type = 'image'): ?array
    {
        $token = $this->config->getReaderPdfApiBearerToken();
        $baseUrl = $this->config->getReaderPdfApiBaseUrl();

        if (!$this->validate($path, $baseUrl, $token)) {
            return null;
        }

        $apiUrl = $baseUrl . 'api/' . $type . '/codes';
        [$response, $httpCode, $curlError] = $this->apiRequest($path, $apiUrl, $token);

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

    /**
     * Guess MIME type from the file extension.
     *
     * @param string $path
     * @return string
     */
    private function guessMimeType(string $path): string
    {
        $pathInfo = $this->ioFile->getPathInfo($path);
        $extension = strtolower((string)($pathInfo['extension'] ?? ''));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    /**
     * Parse barcode codes from an API JSON response.
     *
     * @param string $response
     * @return string[]|null
     */
    private function parseResponse(string $response): ?array
    {
        $results = json_decode($response, true);

        $this->logger->info('Bar Codes API result', is_array($results) ? $results : []);
        if (!is_array($results) || empty($results['codes']) || !is_array($results['codes'])) {
            return null;
        }

        $codes = [];

        foreach ($results['codes'] as $code) {
            $codeType = $code['type'] ?? '';
            if (!empty($code['data']) && in_array($codeType, ['I2/5', 'CODE-128', 'QR-Code'], true)) {
                $codes[] = (string)$code['data'];
            }
        }

        return $codes ?: null;
    }
}
