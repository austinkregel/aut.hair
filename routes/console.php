<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test', function () {
    $connection = new Connection([
        'hosts' => ['kregel.host'],
        'port' => 389,
        'base_dn' => 'dc=kregel,dc=host',
        'username' => 'cn=austin,dc=kregel,dc=host',
        'password' => 'nzc2qkb@uwq_GFT9kna',
    ]);
    
    // Add the connection into the container:
    Container::addConnection($connection);
    
    // Get all objects:
    $objects = Entry::get();
    
    // Get a single object:
    $object = Entry::find('cn=library,dc=kregel,dc=host');
    
    // Getting attributes:
    foreach ($object->memberof as $group) {
        echo $group;
    }
    
});