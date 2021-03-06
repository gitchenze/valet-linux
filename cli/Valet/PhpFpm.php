<?php

namespace Valet;

use DomainException;
use Valet\Contracts\PackageManager;
use Valet\Contracts\ServiceManager;

class PhpFpm
{
    public $pm, $sm, $cli, $files, $version;

    /**
     * Create a new PHP FPM class instance.
     *
     * @param  PackageManager $pm
     * @param  ServiceManager $sm
     * @param  CommandLine  $cli
     * @param  Filesystem  $files
     * @return void
     */
    public function __construct(PackageManager $pm, ServiceManager $sm, CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->pm = $pm;
        $this->sm = $sm;
        $this->version = $this->pm->getPHPVersion();
        $this->files = $files;
    }

    /**
     * Install and configure PHP FPM.
     *
     * @return void
     */
    public function install()
    {
        if (! $this->pm->installed("php{$this->version}-fpm")) {
            $this->pm->ensureInstalled("php{$this->version}-fpm");
        }

        $this->files->ensureDirExists('/var/log', user());

        $this->installConfiguration();

        $this->restart();
    }

    /**
     * Uninstall PHP FPM valet config.
     *
     * @return void
     */
    public function uninstall()
    {
        if ($this->files->exists($this->fpmConfigPath().'/valet.conf')) {
            $this->files->unlink($this->fpmConfigPath().'/valet.conf');
            $this->restart();
        }
    }

    /**
     * Update the PHP FPM configuration to use the current user.
     *
     * @return void
     */
    public function installConfiguration()
    {
        $contents = $this->files->get(__DIR__.'/../stubs/fpm.conf');

        $this->files->putAsUser(
            $this->fpmConfigPath().'/valet.conf',
            str_replace(['VALET_USER', 'VALET_HOME_PATH'], [user(), VALET_HOME_PATH], $contents)
        );
    }

    /**
     * Restart the PHP FPM process.
     *
     * @return void
     */
    public function restart()
    {
        $this->sm->restart($this->fpmServiceName());
    }

    /**
     * Stop the PHP FPM process.
     *
     * @return void
     */
    public function stop()
    {
        $this->sm->stop($this->fpmServiceName());
    }

    /**
     * Determine php service name
     *
     * @return string
     */
    function fpmServiceName() {
        $service = 'php'.$this->version.'-fpm';

        if (strpos($this->cli->run('service ' . $service . ' status'), 'not-found')) {
            return new DomainException("Unable to determine PHP service name.");
        }

        return $service;
    }

    /**
     * Get the path to the FPM configuration file for the current PHP version.
     *
     * @return string
     */
    public function fpmConfigPath()
    {
        return collect([
            '/etc/php/'.$this->version.'/fpm/pool.d', // Ubuntu
            '/etc/php'.$this->version.'/fpm/pool.d', // Ubuntu
            '/etc/php-fpm.d', // Fedora
        ])->first(function ($path) {
            return is_dir($path);
        }, function () {
            throw new DomainException('Unable to determine PHP-FPM configuration folder.');
        });
    }
}
