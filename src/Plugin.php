<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */
declare (strict_types=1);
namespace Oxid_Esales\Unified_Name_Space_Generator;

use Composer\Composer;
use Composer\Event_Dispatcher\Event_Subscriber_Interface;
use Composer\IO\Io_Interface;
use Composer\Plugin\Plugin_Interface;
use Composer\Script\Script_Events;
readonly class Plugin implements Plugin_Interface, Event_Subscriber_Interface
{
    private Io_Interface $io;
    private Composer $composer;
    public function activate(Composer $composer, Io_Interface $io): void
    {
        $this->io = $io;
        $this->composer = $composer;
    }
    public static function get_subscribed_events(): array
    {
        return [Script_Events::POST_INSTALL_CMD => 'callback', Script_Events::POST_UPDATE_CMD => 'callback'];
    }
    public function callback(): void
    {
        $this->require_autoload();
        $this->io->write('<info>Generating OXID eShop unified namespace classes</>');
        $generator = new Generator(new Unified_Name_Space_Class_Map_Provider());
        $generator->cleanup_output_directory();
        $generator->generate();
    }
    public function deactivate(Composer $composer, Io_Interface $io): void
    {
    }
    public function uninstall(Composer $composer, Io_Interface $io): void
    {
    }
    private function require_autoload(): void
    {
        require_once $this->composer->get_config()->get('vendor-dir') . '/autoload.php';
    }
}