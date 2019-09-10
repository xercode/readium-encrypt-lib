<?php

namespace xeBook\Readium\Encrypt\Filesystem\Test;

use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use xeBook\Readium\Encrypt\Filesystem\AwsS3Adapter;

class AwsS3AdapterTest extends TestCase
{
    public function testAwsS3AdapterReadStream()
    {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => 'eu-west-1',
            'debug'   => false,
            'prefix'  => '',
            'credentials'  => [
                'key'       => $_ENV['AWS_AWS_ACCESS_KEY_ID'],
                'secret'    => $_ENV['AWS_SECRET_ACCESS_KEY']
            ]

        ]);

        $adapter = new AwsS3Adapter($s3Client, $_ENV['AWS_S3_BUCKET']);
        $stream = $adapter->readStream('100/100_1393948651_5315f7eb656ca_5315f8b6d7f76.pdf');
        $this->assertInstanceOf(\Psr\Http\Message\StreamInterface::class, $stream);
    }
}
