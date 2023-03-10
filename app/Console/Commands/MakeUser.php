<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeUser extends Command
{
    protected $signature = 'make:user';

    protected $description = 'Command description';

    public function handle()
    {
        $user = new User;
        $user->name = $this->ask('What is your name?');
        $user->email = $this->ask("What is your email address?");
        $user->password = bcrypt($password = Str::random(16));
        $user->save();
        $this->info("Created user");
        $this->warn('    ' .$user->email);
        $this->warn('     '.$password);

        return Command::SUCCESS;
    }
}
