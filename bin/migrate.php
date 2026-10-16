<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/bootstrap.php';
use App\Services\MigrationRunner;
use App\Support\ServiceFactory;
try{$runner=new MigrationRunner(db(),ServiceFactory::migrations(db()),__DIR__.'/../database/migrations');$result=$runner->applyPending();echo 'Applied: '.($result['applied']?implode(', ',$result['applied']):'none').PHP_EOL;echo 'Current schema: '.($result['current']??'none').PHP_EOL;exit(0);}catch(Throwable $e){fwrite(STDERR,'Migration failed: '.$e->getMessage().PHP_EOL);exit(1);}
