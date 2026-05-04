<?php

namespace App\Services;

use OSS\OssClient;
use OSS\Core\OssException;

class OssService
{
    private ?OssClient $client = null;
    private string $bucket;
    private string $endpoint;
    private string $cdnDomain;
    private bool $ssl;

    public function __construct()
    {
        $this->bucket = config('filesystems.disks.oss.bucket');
        $this->endpoint = config('filesystems.disks.oss.endpoint');
        $this->cdnDomain = config('filesystems.disks.oss.cdn_domain', '');
        $this->ssl = config('filesystems.disks.oss.ssl', true);

        $accessKeyId = config('filesystems.disks.oss.access_id');
        $accessKeySecret = config('filesystems.disks.oss.access_key');

        if ($accessKeyId && $accessKeySecret) {
            try {
                $this->client = new OssClient(
                    $accessKeyId,
                    $accessKeySecret,
                    $this->endpoint,
                    $this->ssl
                );
            } catch (OssException $e) {
                throw new \RuntimeException('OSS客户端初始化失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 上传文件到OSS
     */
    public function upload(string $object, string $filePath, array $options = []): bool
    {
        if (!$this->client) {
            throw new \RuntimeException('OSS客户端未初始化');
        }

        try {
            $this->client->uploadFile($this->bucket, $object, $filePath, $options);
            return true;
        } catch (OssException $e) {
            throw new \RuntimeException('OSS上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 上传文件内容到OSS
     */
    public function put(string $object, string $content, array $options = []): bool
    {
        if (!$this->client) {
            throw new \RuntimeException('OSS客户端未初始化');
        }

        try {
            $this->client->putObject($this->bucket, $object, $content, $options);
            return true;
        } catch (OssException $e) {
            throw new \RuntimeException('OSS上传失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取文件URL
     */
    public function url(string $object): string
    {
        // 如果有CDN域名，使用CDN
        if ($this->cdnDomain) {
            $protocol = $this->ssl ? 'https' : 'http';
            return $protocol . '://' . rtrim($this->cdnDomain, '/') . '/' . ltrim($object, '/');
        }

        // 否则使用OSS内网/外网地址
        $protocol = $this->ssl ? 'https' : 'http';
        return $protocol . '://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($object, '/');
    }

    /**
     * 获取临时签名URL（私有Bucket使用）
     */
    public function temporaryUrl(string $object, int $expiration = 3600): string
    {
        if (!$this->client) {
            throw new \RuntimeException('OSS客户端未初始化');
        }

        try {
            return $this->client->signUrl($this->bucket, $object, $expiration);
        } catch (OssException $e) {
            throw new \RuntimeException('生成临时URL失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查文件是否存在
     */
    public function exists(string $object): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            return $this->client->doesObjectExist($this->bucket, $object);
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * 删除文件
     */
    public function delete(string $object): bool
    {
        if (!$this->client) {
            return false;
        }

        try {
            $this->client->deleteObject($this->bucket, $object);
            return true;
        } catch (OssException $e) {
            return false;
        }
    }
    /**
     * 调试日志
     */
    private function debugLog(string $message): void
    {
        error_log($message);
        error_log('OSS Bucket: ' . $this->bucket);
        error_log('OSS Endpoint: ' . $this->endpoint);
        error_log('OSS SSL: ' . $this->ssl);
        error_log('OSS CDN Domain: ' . $this->cdnDomain);
    }
}