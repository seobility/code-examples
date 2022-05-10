<?php

namespace WM;

class Notifier
{


    static function alert($msg = '')
    {
        Session::push('notif-alert', $msg);
    }


    static function getAlert($unset = true)
    {
        $alert = Session::get('notif-alert', []);
        if ($unset) {
            Session::remove('notif-alert');
        }
        return $alert;
    }
}
