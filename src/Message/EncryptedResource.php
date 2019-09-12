<?php


namespace xeBook\Readium\Encrypt\Message;


class EncryptedResource
{
    //  {"content-id":"ce9bf6d3-08f5-4a80-8629-72316039b3da","content-encryption-key":"2Y97koWhbCzYakuTkSTDZZhMbJfio+FWZcki2qAkhaE=","protected-content-location":"\/tmp\/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf","protected-content-length":1344445,"protected-content-sha256":"5611fceb96d31564ebd0b26326698cc23c284595cefba4f228b2121f4fb62717","protected-content-disposition":"210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf","protected-content-type":"application\/pdf+lcp"}

    /**
     * @var string
     */
    public const HashType = 'sha256';

    /**
     * @var string
     */
    private $source;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $location;

    /**
     * @var integer
     */
    private $length;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var string
     */
    private $disposition;

    /**
     * @var string
     */
    private $type;

    /**
     * @var boolean
     */
    private $sendToLicenseServer;

    /**
     * EncryptMessage constructor.
     *
     * @param string $source
     * @param string $id
     * @param string $key
     * @param string $location
     * @param int    $length
     * @param string $hash
     * @param string $disposition
     * @param string $type
     * @param bool   $sendToLicenseServer
     */
    public function __construct(
        string $source,
        string $id,
        string $key,
        string $location,
        int $length,
        string $hash,
        string $disposition,
        string $type,
        bool $sendToLicenseServer
    ) {
        $this->source              = $source;
        $this->id                  = $id;
        $this->key                 = $key;
        $this->location            = $location;
        $this->length              = $length;
        $this->hash                = $hash;
        $this->disposition         = $disposition;
        $this->type                = $type;
        $this->sendToLicenseServer = $sendToLicenseServer;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getDisposition(): string
    {
        return $this->disposition;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isSendToLicenseServer(): bool
    {
        return $this->sendToLicenseServer;
    }
}
