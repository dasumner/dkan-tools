<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Robo\Result;

class initCommands extends \Robo\Tasks
{
    /**
     * Initialize DKAN project directory.
     */
    public function init($opts = ['host' => ''])
    {
        $this->io()->section('Initializing dktl configuration');
        if (file_exists('dktl.yml') && file_exists('src')) {
            $this->io()->note("This project has already been initialized.");
            exit;
        }

        if (file_exists('dktl.yml')) {
            $this->io()->warning('The dktl.yml file already exists in this directory; skipping.');
        } else {
            $this->createDktlYmlFile();
        }

        $this->io()->section('Initializing src directory');
        if (file_exists('src')) {
            $this->io()->warning('The src directory already exists in this directory; skipping.');
        } else {
            $this->createSrcDirectory($opts['host']);
        }
    }

    /**
     * Generates basic configuration for a DKAN project to work with CircleCI.
     */
    public function initCircleCI() {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();
        return $this->taskExec("cp -r {$dktl_dir}/assets/.circleci {$project_dir}")->run();
    }

    /**
     * Generates basic configuration for a DKAN project to work with ProboCI.
     */
    public function initProboCI() {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();
        $collection = $this->collectionBuilder();
        $collection->addTask($this->taskExec("cp -r {$dktl_dir}/assets/.probo.yml {$project_dir}"));
        $collection->addTask($this->taskExec("cp -r {$dktl_dir}/assets/settings.probo.php {$project_dir}/src/site"));
        return $collection->run();

    }

    private function createDktlYmlFile()
    {
        $dktlRoot = Util::getDktlDirectory();
        $f = 'dktl.yml';
        $result = $this->taskWriteToFile($f)
            ->textFromFile("$dktlRoot/assets/dktl.yml")
            ->run();

        $this->directoryAndFileCreationCheck($result, $f);
    }

    private function createSrcDirectory($host = "")
    {
        $this->_mkdir('src');

        $directories = ['docker', 'make', 'modules', 'themes', 'site', 'tests', 'script', 'command'];

        foreach ($directories as $directory) {
            $dir = "src/{$directory}";

            $result = $this->_mkdir($dir);

            $this->directoryAndFileCreationCheck($result, $dir);
        }

        $this->createMakeFiles();
        $this->createSiteFilesDirectory();
        $this->createSettingsFiles($host);
        $this->setupScripts();
    }

    private function setupScripts() {
        $dktlRoot = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();

        $files = ['deploy', 'deploy.custom'];

        foreach ($files as $file) {
            $f = "src/script/{$file}.sh";

            $task = $this->taskWriteToFile($f)
                ->textFromFile("{$dktlRoot}/assets/script/{$file}.sh");
            $result = $task->run();
            $this->_exec("chmod +x {$project_dir}/src/script/{$file}.sh");

            $this->directoryAndFileCreationCheck($result, $f);
        }
    }

    private function createMakeFiles()
    {
        $dktlRoot = Util::getDktlDirectory();

        $files = ['drupal', 'dkan'];

        foreach ($files as $file) {
            $f = "src/make/{$file}.make";

            $task = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/drush/template.make.yml");
            if ($file == "drupal") {
                $task->text("defaults:\n  projects:\n    subdir: contrib\n");
                $task->text("projects:\n  environment:\n    version: '1.0'\n  environment_indicator:\n    version: '2.9'");
            }
            $result = $task->run();

            $this->directoryAndFileCreationCheck($result, $f);
        }
    }

    private function createSiteFilesDirectory()
    {
        $directory = 'src/site/files';
        $this->_mkdir($directory);
        $result = $this->_exec("chmod 777 {$directory}");

        $this->directoryAndFileCreationCheck($result, $directory);
    }

    private function createSettingsFiles($host = "")
    {
        $dktlRoot = Util::getDktlDirectory();

        $settings = ["settings.php", "settings.docker.php"];

        foreach ($settings as $setting) {
            $f = "src/site/{$setting}";
            $result = $this->taskWriteToFile($f)
                ->textFromFile("$dktlRoot/assets/site/{$setting}")
                ->run();
            $this->directoryAndFileCreationCheck($result, $f);
        }

        if (!empty($host)) {
            $this->initHost($host);
        }
    }

    /**
     * Initialize host settings.
     *
     * @todo Fix opts, make required.
     */
    private function initHost($host)
    {
        $dktlRoot = Util::getDktlDirectory();
        $settingsFile = "settings.$host.php";
        if (!$host) {
            throw new \Exception("Host not specified.");
            exit;
        }
        if (!file_exists("$dktlRoot/assets/site/$settingsFile")) {
            $this->io()->warning("Host settings for '$host' not supported; skipping.");
            exit;
        }
        if (!file_exists('site')) {
            throw new \Exception("The project's site directory must be initialized before adding host settings.");
            exit;
        }
        if (file_exists("site/$settingsFile")) {
            $this->io()->warning("Host settings for '$host' already initialized; skipping.");
            exit;
        }
        $result = $this->taskWriteToFile("site/settings.$host.php")
            ->textFromFile("$dktlRoot/assets/site/settings.$host.php")
            ->run();

        return $result;
    }

    private function directoryAndFileCreationCheck(Result $result, $df)
    {
        if ($result->getExitCode() == 0 && file_exists($df)) {
            $this->io()->success("{$df} was created.");
        } else {
            $this->io()->error("{$df} was not created.");
            exit;
        }
    }
}
