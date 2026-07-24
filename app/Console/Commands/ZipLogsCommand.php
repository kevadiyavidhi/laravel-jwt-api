<?php

namespace App\Console\Commands;

use File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class ZipLogsCommand extends Command
{
    protected $signature = 'logs:zip';
    // name of command
    // php artisan logs:zip
    //         ↓
    // Signature Match
    //         ↓
    // ZipLogsCommand
    //         ↓
    // handle()

    protected $description = 'Zip old Laravel log files';
    // when you write a php artisan list
    // show => logs:zip   Zip old Laravel log files //like this

    public function handle()
    {
        $logPath = storage_path('logs');
        // storage_path() is laravel helper

        // $files = glob($logPath.'/*.log');
        // glob is laravel built in function
        // it returns every log file with .log extention
        // laravel.
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($logPath)
        );

        // traverse multidimensional data

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'log') {
                $files[] = $file->getPathname();
            }
        }

        foreach ($files as $file) {

            $fileDate = date('Y-m-d', filemtime($file));

            if ($fileDate === date('Y-m-d')) {
                continue;
            }
            // if date is today then skip it and move to next loop

            $zipFile = $file.'.zip';
            // laravel.log + .zip = laravel.log.zip

            if (file_exists($zipFile)) {
                continue;
            }
            // if log zip file is exist it will skip

            $zip = new ZipArchive;

            if ($zip->open($zipFile, ZipArchive::CREATE) === true) {

                $zip->addFile($file, basename($file));
                $zip->close();

                unlink($file);

                $this->info('Zipped: '.basename($file));
            }

            Log::info('Log File Zipped', [
                'file' => basename($file),
            ]);
        }

        return Command::SUCCESS;
    }
}
