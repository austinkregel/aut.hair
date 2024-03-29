<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('admin.{jobId}', function ($user) {
    return in_array($user->email, config('auth.admin_emails'));
});
Broadcast::channel('user.{userId}', function ($user, $id) {
    return $user->id == $id;
});
