<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Canal admin.notifications : accès réservé aux rôles admin / staff.
| Frontend : Echo.private('admin.notifications').listen('.AdminNotification', cb)
*/

Broadcast::channel('admin.notifications', function ($user) {
    return $user->hasRole('admin') || $user->hasRole('staff');
});

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
