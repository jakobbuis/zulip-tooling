<?php

namespace Deployer;

require 'recipe/composer.php';

set('application', 'zulip-tooling');
set('repository', 'git@github.com:jakobbuis/zulip-tooling.git');
set('default_stage', 'production');
set('keep_releases', 3);

set('shared_files', ['.env']);

set('ssh_multiplexing', false);

host('jakobbuis.nl')
    ->set('hostname', 'jakobbuis.nl')
    ->set('remote_user', 'jakob')
    ->set('branch', 'main')
    ->set('deploy_path', '/srv/zulip-tooling');
