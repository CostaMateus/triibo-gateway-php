<?php

namespace App\Console\Commands;

use File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete images from on folder \'images/estabelecimentos/\' and \'images/promos/\'.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        echo "DEL : Excluindo imagens\n";
        Log::info("DEL : Excluindo imagens");

        $paths = [
            'images/estabelecimentos/',
            'images/promos/'
        ];

        foreach ($paths as $p)
        {
            $folder = public_path($p);
            File::deleteDirectory($folder, true);
        }

        echo "DEL : Excluido com sucesso\n";
        Log::info("DEL : Excluido com sucesso");
    }
}
