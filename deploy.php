<?php

use function Deployer\after;
use function Deployer\host;
use function Deployer\set;
use function Deployer\task;

require 'vendor/deployer/deployer/recipe/common.php';

// executable and variable directories
set('bin_dir', 'bin');
set('var_dir', 'var');

//  shared dirs
//set('shared_dirs', ['var/logs', 'var/cache']);
//  writable dirs
//set('writable_dirs', ['var/logs', 'var/cache']);

// shared files
set('shared_files', ['.env']);

// Project repository
set('repository', 'git@github.com:xercode/readium-encrypt-tool.git');
set('writable_use_sudo', true);

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);
set('ssh_type', 'native');
set('ssh_multiplexing', true);

// Project name
set('application', 'readium-encrypt-tool');
set('keep_releases', 5);


/**
 * Main task
 */
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:clear_paths',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy your project');

// Display success message on completion
after('deploy', 'success');


// Hosts
host('18.202.55.190')
    ->stage('staging')
    ->user('xebook')
    ->set('deploy_path', '~/{{application}}')
    ->set('branch', 'release')
;
