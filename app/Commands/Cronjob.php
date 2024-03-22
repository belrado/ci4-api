<?php namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Psr\Log\LoggerInterface;

class Cronjob extends BaseCommand
{
    protected $group       = 'cronjob';
    protected $name        = 'cronjob';
    protected $description = '클론잡을 실행합니다.';
    protected $monitor;
    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);
        ini_set("memory_limit","-1");
        ini_set("max_execution_time",60*60*24);



        $this->monitor = new Monitor();
    }


    public function run(array $params)
    {
        CLI::write($this->description,'green');
        if(!isset($params[0]))
        {
            CLI::write("명령어를 입력해 주세요");
            exit;
        }

        $act = $params[0];

        if($act=='monitor')
        {
            //$this->monitor->connectCheck();
            //$this->monitor->misscallCheck();
        }

    }

}