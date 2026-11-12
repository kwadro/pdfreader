<?php

declare(strict_types=1);

namespace Kvadro\PdfReader\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const PDF_LOADER_ENABLED = 'kvadro_pdfreader/general/enabled';
    public const PDF_LOADER_API_BASE_URL_PATH = 'kvadro_pdfreader/general/reader_api_base_url';
    public const PDF_LOADER_API_BEARER_TOKEN_PATH = 'kvadro_pdfreader/general/reader_api_bearer_token';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Check whether PDF Reader is enabled for the given store.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnable(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PDF_LOADER_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the PDF Reader API base URL with a trailing slash.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReaderPdfApiBaseUrl(?int $storeId = null): string
    {
        $url = (string)$this->scopeConfig->getValue(
            self::PDF_LOADER_API_BASE_URL_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $url !== '' ? rtrim($url, '/') . '/' : '';
    }

    /**
     * Return the decrypted PDF Reader API bearer token.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getReaderPdfApiBearerToken(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::PDF_LOADER_API_BEARER_TOKEN_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->decryptConfigValue(is_string($value) ? $value : null);
    }

    /**
     * Values saved with Magento\Config\Model\Config\Backend\Encrypted are stored as ciphertext.
     *
     * @param string|null $value
     * @return string
     */
    private function decryptConfigValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decrypted = $this->encryptor->decrypt($value);

        return $decrypted !== '' ? $decrypted : $value;
    }
}
