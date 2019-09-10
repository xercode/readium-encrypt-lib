<?php


namespace xeBook\Readium\Encrypt\Filesystem;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Psr\Log\LoggerAwareTrait;

class AwsS3Adapter
{
    use LoggerAwareTrait;

    /**
     * @var S3Client
     */
    private $client;

    /**
     * @var string
     */
    private $bucket;

    /**
     * AwsS3Adapter constructor.
     *
     * @param S3Client $client the aws client
     * @param string   $bucket the bucket name
     */
    public function __construct(S3Client $client, string $bucket)
    {
        $this->client = $client;
        $this->bucket = $bucket;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    private function logger($level, $message, array $context = array())
    {
        if($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return \Psr\Http\Message\StreamInterface|null
     */
    public function readStream($path)
    {
        try {

            $this->logger('debug', 'getObject', ['path' => $path]);

            /** @var \Aws\Result $response */
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $path
            ]);

            $this->logger('debug', 'Aws\Result', ['result' => $result]);

            return $result->get('Body');

        } catch (S3Exception $e) {
            $this->logger('error', $e->getMessage(), ['path' => $path]);
            return null;
        }
    }
}
