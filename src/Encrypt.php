<?php


namespace xeBook\Readium\Encrypt;

use Psr\Log\LoggerAwareTrait;
use xeBook\Readium\Encrypt\Exception\InvalidArgumentException;
use xeBook\Readium\Encrypt\Exception\EncryptException;
use xeBook\Readium\Encrypt\Exception\FilesystemException;

class Encrypt
{
    use LoggerAwareTrait;

    private const SUCCESS_CODE = 0;

    private const ERROR_CODES = [
        10 => 'Error creating json addedPublication.',
        20 => 'Error notifying the License Server.',
        30 => 'Error encrypting the publication.',
        40 => 'Error encrypting.',
        41 => 'Error opening output.',
        42 => 'Error opening packaged web publication.',
        43 => 'Error writing output file.',
        50 => 'Error building Web Publication package from PDF.',
        51 => 'Error reading the epub content.',
        60 => 'Error opening the epub file.',
        65 => 'Error on generate new contentID.',
        70 => 'Error opening input file.',
        80 => 'Error incorrect parameters for License Server.',

    ];

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        if($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * @var string
     */
    private $encryptTool;

    /**
     * @var string
     */
    private $licenseServerEndpoint;

    /**
     * @var string
     */
    private $licenseServerUsername;

    /**
     * @var string
     */
    private $licenseServerPassword;

    /**
     * @var string|null
     */
    private $licenseServerProfile;

    /**
     * Encrypt constructor.
     *
     * @param string      $encryptTool
     * @param string|null $licenseServerEndpoint
     * @param string|null $licenseServerUsername
     * @param string|null $licenseServerPassword
     * @param string|null $licenseServerProfile
     */
    public function __construct(
        string $encryptTool,
        ?string $licenseServerEndpoint = null,
        ?string $licenseServerUsername = null,
        ?string $licenseServerPassword = null,
        ?string $licenseServerProfile = 'basic'
    ) {
        if (!file_exists($encryptTool) ) {
            throw new InvalidArgumentException(
                'The encrypt tool '.$encryptTool.' not exits.', 10
            );
        }
        if (!is_readable($encryptTool)) {
            throw new InvalidArgumentException(
                'The encrypt tool '.$encryptTool.' is not readable.', 10
            );
        }

        if (!is_executable($encryptTool)) {
            throw new InvalidArgumentException(
                'The encrypt tool '.$encryptTool.' is not executable.', 10
            );
        }

        $this->encryptTool           = $encryptTool;
        $this->licenseServerEndpoint = $licenseServerEndpoint;
        $this->licenseServerUsername = $licenseServerUsername;
        $this->licenseServerPassword = $licenseServerPassword;
        $this->licenseServerProfile   = $licenseServerProfile;
    }


    /**
     * lcpencrypt protects an epub/pdf file for usage in an lcp environment
     *
     * @param string      $input               source epub/pdf file locator (file system or http GET)
     * @param bool        $sendToLicenseServer optional send to the License server
     * @param string|null $contentId           optional content identifier, if omitted a new one will be generated
     * @param string|null $output              optional target location for protected content (file system or http PUT)
     *                                         optional, file path of the target protected content.  If not set put file int tmp file system.
     * @return array
     */
    public function run(
        string $input,
        ?bool $sendToLicenseServer = true,
        ?string $contentId = null,
        ?string $output = null
    ) {

        if (!file_exists($input) || !is_readable($input)) {
            throw new FilesystemException('The input path '.$input.' is not readable.', 40);
        }

        if ($output === null) {
            $extension = pathinfo($input, 'PATHINFO_EXTENSION');
            $filename = basename($input, $extension);

            $extensionEncryptedFile = null;

            if($extension == 'pdf') {
                $extensionEncryptedFile = '.lcpdf';
            } elseif($extension == 'epub') {
                $extensionEncryptedFile = '.lcp';
            } else {
                $extensionEncryptedFile = '.encrypted';
            }

            $output   = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename.$extensionEncryptedFile;
        }

        $command = sprintf('%s -input "%s" -profile "%s" ', $this->encryptTool, $input, $this->licenseServerProfile);

        if ($sendToLicenseServer == true) {
            $command .= sprintf(
                '-lcpsv "%s" -login "%s" -password "%s" ',
                $this->licenseServerEndpoint,
                $this->licenseServerUsername,
                $this->licenseServerPassword
            );
        }

        if ($contentId !== null) {
            $command .= sprintf('-contentid "%s" ', $contentId);
        }

        if ($output !== null) {
            $command .= sprintf('-output "%s" ', $output);
        }

        $outputExecCommand         = [];
        $returnExitCodeExecCommand = null;
        $returnExec                = exec($command, $outputExecCommand, $returnExitCodeExecCommand);
        $this->log('info', 'Run Encrypt command', ['command' => $command, 'outputExecCommand' => $outputExecCommand, 'returnExitCodeExecCommand' => $returnExitCodeExecCommand, 'returnExec' => $returnExec]);

        if ($returnExitCodeExecCommand !== self::SUCCESS_CODE) {
            if (array_key_exists($returnExitCodeExecCommand, self::ERROR_CODES)) {
                throw new EncryptException(self::ERROR_CODES[$returnExitCodeExecCommand], $returnExitCodeExecCommand);
            }

            throw new EncryptException($returnExec, $returnExitCodeExecCommand);

        }

        // transform responseToJson
        $json = '';
        // clean first line with text License Server was notified
        if ($sendToLicenseServer == true) {
            unset($outputExecCommand[0]);
        }

        foreach ($outputExecCommand as $item) {
            $json .= $item;
        }

        return json_decode($json, true);
    }

}
