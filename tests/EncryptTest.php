<?php

namespace xeBook\Readium\Encrypt\Test;


use PHPUnit\Framework\TestCase;
use xeBook\Readium\Encrypt\Encrypt;
use xeBook\Readium\Encrypt\Exception\EncryptException;
use xeBook\Readium\Encrypt\Exception\FilesystemException;
use xeBook\Readium\Encrypt\Exception\InvalidArgumentException;

class EncryptTest extends TestCase
{

    private $encryptTool;
    private $licenseServerEndpoint;
    private $licenseServerUsername;
    private $licenseServerPassword;
    private $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath             = realpath(__DIR__.'/fixtures');

        $this->encryptTool              = $_ENV['ENCRYPT_TOOL'];
        $this->licenseServerEndpoint    = $_ENV['LICENSE_SERVER_ENDPOINT'];
        $this->licenseServerUsername    = $_ENV['LICENSE_SERVER_USERNAME'];
        $this->licenseServerPassword    = $_ENV['LICENSES_ERVER_PASSWORD'];
    }

    public function filePaths()
    {
        return [
            ['/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf'],
            ['/9788415410478.epub']
        ];
    }

    public function testEncryptEncryptToolNotFound()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(10);
        new Encrypt('/user/bin/encrypt-tool', 'http://www.foo.lo', 'username', 'password');
    }

    public function testEncryptLicenseServerHaveInvalidUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(20);
        new Encrypt($this->encryptTool, 'fo');
    }

    public function testEncryptLicenseServerUsernameIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(30);
        new Encrypt($this->encryptTool, $this->licenseServerEndpoint);
        new Encrypt($this->encryptTool, $this->licenseServerEndpoint, null, 'foo');
    }

    public function testEncryptLicenseServerPasswordIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(30);
        new Encrypt($this->encryptTool, $this->licenseServerEndpoint);
        new Encrypt($this->encryptTool, $this->licenseServerEndpoint,  'foo');
    }

    /**
     * @dataProvider filePaths()
     *
     * @param string $inputFile the input file for encrypt
     */
    public function testEncryptRun(string $inputFile)
    {
        $encrypt = new Encrypt($this->encryptTool, $this->licenseServerEndpoint, $this->licenseServerUsername, $this->licenseServerPassword);
        $output  = $encrypt->run($this->fixturesPath.$inputFile);
        $this->assertNotNull($output);
        $this->assertIsArray($output);
        $this->assertArrayHasKey('content-encryption-key', $output);
        $this->assertArrayHasKey('content-id', $output);
        $this->assertArrayHasKey('protected-content-disposition', $output);
        $this->assertArrayHasKey('protected-content-length', $output);
        $this->assertArrayHasKey('protected-content-location', $output);
        $this->assertArrayHasKey('protected-content-sha256', $output);
        $this->assertArrayHasKey('protected-content-type', $output);
    }

    public function testEncryptRunInputFileNotFound()
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionCode(40);
        $encrypt = new Encrypt($this->encryptTool, $this->licenseServerEndpoint, $this->licenseServerUsername, $this->licenseServerPassword);
        $encrypt->run('/tmp/foo');
    }

    public function testEncryptRunOutputFileNotWriting()
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionCode(50);
        $input = $this->fixturesPath.'/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf';
        $encrypt = new Encrypt($this->encryptTool, $this->licenseServerEndpoint, $this->licenseServerUsername, $this->licenseServerPassword);

        $encrypt->run( $input, 'basic', true, '123456', '/123456.failed');
    }

    public function testEncryptRunErrorOnNotifyingToServer()
    {
        $this->expectException(EncryptException::class);
        $this->expectExceptionCode(20);
        $input = $this->fixturesPath.'/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf';
        $encrypt = new Encrypt($this->encryptTool,'http://127.0.0.1', $this->licenseServerUsername, $this->licenseServerPassword);

        $encrypt->run( $input, 'basic', true, '123456');
    }
}
