<?php

namespace xeBook\Readium\Encrypt\Command;

use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use xeBook\Readium\Encrypt\Encrypt;
use xeBook\Readium\Encrypt\Filesystem\AwsS3Adapter;

class EncryptCommand extends Command
{
    use LockableTrait;

    private const scheme_file = 'file';
    private const scheme_s3 = 's3';


    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'app:encrypt';

    /**
     * @var Encrypt
     */
    private $encrypt;

    /**
     * @var AwsS3Adapter
     */
    private $awsS3Adapter;

    /**
     * @var string
     */
    private $profile;

    /**
     * EncryptCommand constructor.
     *
     * @param Encrypt      $encrypt
     * @param AwsS3Adapter $awsS3Adapter
     * @param string       $profile
     */
    public function __construct(Encrypt $encrypt, AwsS3Adapter $awsS3Adapter, string $profile = 'basic')
    {
        $this->encrypt      = $encrypt;
        $this->awsS3Adapter = $awsS3Adapter;
        $this->profile      = $profile;

        parent::__construct(self::$defaultName);
    }


    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->addArgument('source', InputArgument::REQUIRED, 'source epub/pdf file locator (file system or http GET). Use prefix file:/// for use filesystem use s3:// for download form s3');

        $this->addOption('output', '-o',InputOption::VALUE_OPTIONAL, 'optional, file path of the target protected content.  If not set put file int tmp file system.');
        $this->addOption('contentId', '-id',InputOption::VALUE_OPTIONAL, 'optional content identifier, if omitted a new one will be generated');


        $this->addOption('sendToLicenseServer', '-s', InputOption::VALUE_NONE, 'optional, file path of the target protected content.  If not set put file int tmp file system.');

        $this->setDescription('A command line utility for content encryption');
        $this->setHelp('The goal of this cross-platform command line executable is to be usable on any kind of processing pipeline.');
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @return int|null null or 0 if everything went fine, or an error code
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io              = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            $io->warning('The command is already running in another process.');

            return 0;
        }

        $inputFilename      = $input->getArgument('source');
        $outputFilename         = $input->getOption('output');
        $contentId              = $input->getOption('contentId');
        $sendToLicenseServer    = $input->getOption('sendToLicenseServer');

        $scheme             = parse_url($inputFilename, PHP_URL_SCHEME);
        $path               = parse_url($inputFilename, PHP_URL_PATH);
        $host               = parse_url($inputFilename, PHP_URL_HOST);

        if($scheme !== self::scheme_s3 && $scheme !== self::scheme_file) {
            $io->error('Invalid protocol. Use s3 or file. Example s3://210/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf or file:///210/210_1567702120_5d713c68c4fbc_5d713c68c5068.pdf.');
            $this->release();
            return -1;
        }

        $sourceFile = null;
        if($scheme == self::scheme_s3) {
            $sourceFile = $this->download($host.$path);
        }elseif($scheme == self::scheme_file) {
            $sourceFile = $path;
        }

        if(!file_exists($sourceFile)) {
            return $io->error(sprintf('The file %s not found.', $sourceFile));

            $this->release();

            return -2;
        }

        $encryptResponse = $this->encrypt->run($sourceFile, $this->profile, $sendToLicenseServer, $contentId, $outputFilename);

        $io->text(json_encode($encryptResponse));

        $this->release();

        return 0;
    }


    private function download(string $path)
    {
        $response = $this->awsS3Adapter->readStream($path);
        $filename = basename($path);
        if($response == null) {
            return null;
        }

        $downloadPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;

        $numberOfBytesWritten = $response->write($downloadPath);

        if($numberOfBytesWritten > 0) {
            return $downloadPath;
        }

        return null;

    }
}
