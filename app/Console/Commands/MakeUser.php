<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class MakeUser extends Command
{
    protected $signature = 'make:user';

    protected $description = 'Command description';

    public function handle(): int
    {
        /** @var CreatesNewUsers $createAction */
        do {
            $createAction = app(CreatesNewUsers::class);

            $data = [
                'name' => $this->ask('What is your name?'),
                'email' => $this->ask('What is your email address?'),
                'password' => $password = Str::random(16),
                'password_confirmation' => $password,
                'terms' => true,
            ];

            try {
                $user = $createAction->create($data);
            } catch (ValidationException $exception) {
                $this->error($exception->getMessage());
            }
        } while (empty($user));

        $user->markEmailAsVerified();

        $this->info('Created user');

        $this->info('You can login using the following credentials.');
        $this->warn('    '.$user->email);
        $this->warn('     '.$password);

        return Command::SUCCESS;
    }
}
