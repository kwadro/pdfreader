<?php

declare(strict_types=1);

namespace Kvadro\PdfReader\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const PDF_LOADER_ENABLED = 'kvadro_pdfreader/general/enabled';
    const PDF_LOADER_API_BASE_URL_PATH = 'kvadro_pdfreader/general/reader_api_base_url';
    const PDF_LOADER_API_BEARER_TOKEN_PATH = 'kvadro_pdfreader/general/reader_api_bearer_token';

    private ScopeConfigInterface $scopeConfig;
    private EncryptorInterface $encryptor;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isEnable($storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::PDF_LOADER_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getReaderPdfApiBaseUrl($storeId = null): string
    {
        $url = (string)$this->scopeConfig->getValue(
            self::PDF_LOADER_API_BASE_URL_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $url !== '' ? rtrim($url, '/') . '/' : '';
    }

    /**
     * @param $storeId
     * @return string
     */
    public function getReaderPdfApiBearerToken($storeId = null): string
    {
        $value = $this->scopeConfig->getValue(
            self::PDF_LOADER_API_BEARER_TOKEN_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->decryptConfigValue($value);
    }

    /**
     * Values saved with Magento\Config\Model\Config\Backend\Encrypted are stored ciphertext in core_config_data.
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
