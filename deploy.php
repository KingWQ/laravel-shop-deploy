<?php
namespace Deployer;

// 引入官方的 Laravel 部署脚本
require 'recipe/laravel.php';

// 设置一个名为 application 的环境变量，值为 my_project
set('application', 'my_project');

set('repository', 'https://github.com/KingWQ/laravel-shop.git');
add('shared_files', []);
add('shared_dirs', []);
set('writable_dirs', []);


host('第一台')
    ->user('root')
    ->identityFile('~/.ssh/laravel.pem')
    ->become('www-data')
    ->set('deploy_path', '/var/www/laravel7');

host('第二台')
    ->user('root')
    ->identityFile('~/.ssh/laravel.pem')
    ->become('www-data')
    ->set('deploy_path', '/var/www/laravel7');

host('第三台')
    ->user('root')
    ->identityFile('~/.ssh/laravel.pem')
    ->become('www-data')
    ->set('deploy_path', '/var/www/laravel7');

// 1: 定义一个复制.env
desc('Upload .env file');
task('env:upload', function(){
   upload('.env', '{{release_path}}/.env');
});

//2: 定义一个前端编译的任务
desc('Yarn');
task('deploy:yarn', function(){
    // release_path 是 Deployer 的一个内部变量，代表当前代码目录路径
    // run() 的默认超时时间是 5 分钟，而 yarn 相关的操作又比较费时，因此我们在第二个参数传入 timeout = 600，指定这个命令的超时时间是 10 分钟
    run('cd {{release_path}} && SASS_BINARY_SITE=http://npm.taobao.org/mirrors/node-sass yarn && yarn production', ['timeout' => 600]);
});

//3: 把 composer 的 vendor 目录也加进来, node_modules 目录复制过来，然后再执行 yarn，这样就只需要下载有变更的部分，大大加快这个步骤的速度。
add('copy_dirs', ['node_modules', 'vendor']);

//4: 定义一个执行 es:migrate命令的任务
desc('Execute elasticsearch migrate');
task('es:migrate', function(){
    // {{bin/php}} 是 Deployer 内置的变量，是 PHP 程序的绝对路径。
    run('{{bin/php}} {{release_path}}/artisan es:migrate');
})->once();

//5: 停止Horizon进程然后再重新启动 使其加载版本的代码
desc('Restart Horizon');
task('horizon:terminate',function(){
    run('{{bin/php}} {{release_path}}/artisan horizon:terminate');
});

after('deploy:shared', 'env:upload');
after('deploy:vendors', 'deploy:yarn');
after('artisan:migrate', 'es:migrate');
//deploy:symlink 任务是将 current 链接到最新的代码目录
after('deploy:symlink', 'horizon:terminate');

before('deploy:vendors', 'deploy:copy_dirs');

after('deploy:failed', 'deploy:unlock');

before('deploy:symlink', 'artisan:migrate');

