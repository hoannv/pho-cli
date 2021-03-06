<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pho\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

use Composer\Console\Application as ComposerApp;
use Composer\Command\CreateProjectCommand;

class InitCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initializes a new project')
            ->addArgument('destination', InputArgument::REQUIRED, 'The directory where the application will be hosted.')
            ->addArgument('skeleton', InputArgument::OPTIONAL, 'The template to copy. Either one of the presets (Basic[default], Twitter, Twitter-simple, Facebook) or a directory with your **compiled** pgql files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = "php " . dirname(__FILE__).'/../../../bin/composer.phar ';

        $dir = $input->getArgument('destination');
        if(file_exists($dir)) {
            $output->writeln(sprintf('<error>The directory "%s" already exists.</error>', $dir));
            exit(1);
        }
        mkdir($dir);
        $skeleton = $input->getArgument("skeleton");
        /*
        $process = new Process(
            //sprintf('cd %s && echo "'.addslashes('{"minimum-stability":"dev"}').'" > composer.json && '.dirname(__FILE__).'/../../../vendor/bin/composer require phonetworks/pho-kernel', $dir)
            //sprintf('cp -pR '.dirname(__FILE__).'/../../../vendor/phonetworks/pho-kernel/* %s && cd %s && '.$composer.' install', $dir, $dir)
            sprintf('')
        );
        */
        $input = new ArrayInput(array(
            'command' => 'create-project',
            '--stability' => 'dev',
            'package' => 'phonetworks/pho-kernel',
            'directory' => $dir
             ));

        //Create the application and run it with the commands
        $composer = new ComposerApp();
        $composer->setAutoExit(false); 
        $composer->run($input);
        

        file_put_contents($dir.DIRECTORY_SEPARATOR.".env", 
<<<eos
DATABASE_TYPE="redis"
DATABASE_URI"="redis://127.0.0.1:6379"
STORAGE_TYPE="filesystem"
STORAGE_URI="filesystem:// /tmp/pho"   
eos
);

        $skeleton_use = 0; // no use, 1: template, 2: compiled
        if(in_array($skeleton, ["twitter-simple", "twitter-full", "facebook", "basic"])) {
            $skeleton_use = 1;
            chdir($dir);
            unlink($dir.DIRECTORY_SEPARATOR."composer.json");
            copy($dir.DIRECTORY_SEPARATOR."presets".DIRECTORY_SEPARATOR.$skeleton, $dir.DIRECTORY_SEPARATOR."composer.json");
            $input = new ArrayInput(array(
            'command' => 'update'
             ));
            $composer->run($input);
        }
        elseif(is_dir($skeleton)) {
            $skeleton_use = 2;
            chdir($dir);
            mkdir(".compiled");
            exec("cp -pR ".escapeshellarg($skeleton.DIRECTORY_SEPARATOR)."* ".escapeshellarg($dir.DIRECTORY_SEPARATOR.".compiled"));
        }

        $pointer = "<comment>Your project can be found at ".$dir;
        switch($skeleton_use) {
            case 0:
                $output->writeln("<info>Project initialized with basic settings.</info>");
                $output->writeln($pointer);
                $output->writeln("<comment>Include (or examine) play.php to get started quickly.</comment>");
                break;
            case 1:
                $output->writeln(sprintf("<info>Project initialized with the %s skeleton.</info>", $skeleton));
                $output->writeln($pointer);
                $output->writeln("<comment>Include (or examine) play.php to get started quickly.</comment>");
                break;
            case 2:
                $output->writeln("<info>Project initialized with your compiled pgql files.</info>");
                $output->writeln($pointer);
                $output->writeln("<comment>Dismiss play.php and customize play-custom.php in accordance to your settings.</comment>");
                break;
        }

        exit(0);

    }
}
