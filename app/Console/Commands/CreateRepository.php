<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create a new repository and interface class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $repositoryDirectory = app_path("Repository/$name.php");
        $repositoryInterfaceDirectory = app_path("Repository/Interface/I$name.php");
        if(file_exists($repositoryDirectory) || file_exists($repositoryInterfaceDirectory))
        {
            $this->error("Repository or Interface class already exists");
            return;
        }
        $repositoryContent =
        <<<php
        <?php
        namespace App\Repository;
        use App\Repository\Interface\I$name;
        class $name implements I$name
        {
            //
        }
        
        php;
        $repositoryInterfaceContent =
        <<<php
        <?php
        namespace App\Repository\Interface;
        interface I$name 
        {
            //
        }
        ?>
        php;
        file_put_contents($repositoryDirectory, $repositoryContent);
        file_put_contents($repositoryInterfaceDirectory, $repositoryInterfaceContent);
        $this->info("Repository and Interface classes created successfully");
        return;
    }
}
