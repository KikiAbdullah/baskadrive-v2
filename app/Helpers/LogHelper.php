<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\UserLog;
use Carbon\Carbon;

class LogHelper
{
    public function storeLogMsg($msg, $type)
    {
        $name = auth()->user()->name ?? 'System';
        $item = [
            'user_id' => auth()->user()->id ?? '1',
            'action' => $this->typeFormatted($type),
            'message' => $name.' melakukan '.$msg,
        ];

        UserLog::create($item);

        return true;
    }

    public function storeLogMsgWebApi($userid, $msg, $type)
    {
        $name = User::find($userid)->name ?? 'System';
        $item = [
            'user_id' => $userid ?? '1',
            'action' => $this->typeFormatted($type),
            'message' => $name.' melakukan '.$msg,
        ];

        UserLog::create($item);

        return true;
    }

    public function storeLog($type, $nomor, $menu)
    {
        $item = [
            'user_id' => auth()->user()->id ?? '1',
            'menu' => $menu,
            'action' => $this->typeFormatted($type),
            'message' => $this->makeLogMessage(auth()->user()->name ?? 'System', $type, $nomor, $menu),
        ];

        UserLog::create($item);

        return true;
    }

    public function storeLogWebapi($user, $name, $type, $nomor, $menu)
    {
        $item = [
            'user_id' => $user,
            'menu' => $menu,
            'action' => $this->typeFormatted($type),
            'message' => $this->makeLogMessage($name, $type, $nomor, $menu),
        ];

        UserLog::create($item);

        return true;
    }

    public function makeLogMessage($name, $type, $nomor, $menu)
    {
        $message = '<p>User <b>'.$name.'</b> melakukan '.$this->typeFormatted($type);

        $message .= $this->textMenuDanNomor($menu, $nomor);

        return $message;
    }

    public function typeFormatted($type)
    {
        $str = $type;
        switch ($type) {
            case 'add':
                $str = 'Penambahan';
                break;
            case 'edit':
                $str = 'Perubahan';
                break;
            case 'update':
                $str = 'Perubahan';
                break;
            case 'delete':
                $str = 'Penghapusan';
                break;
            case 'approve':
                $str = 'Approve';
                break;
            case 'approve1':
                $str = 'Approve';
                break;
            case 'approve2':
                $str = 'Approve';
                break;
            case 'approve3':
                $str = 'Approve';
                break;
            case 'reject':
                $str = 'Reject';
                break;
            case 'submit':
                $str = 'Submit';
                break;
            case 'verifikasi':
                $str = 'Verifikasi';
                break;
            case 'posting':
                $str = 'Posting';
                break;
        }

        return $str;
    }

    public function textTanggal()
    {
        return ' pada tanggal <b>'.Carbon::now()->format('d F Y H:i:s').'</b> </p>';
    }

    public function textMenuDanNomor($menu, $nomor)
    {
        return ' data di menu <b>'.ucwords($menu).'</b> dan id/nomor <b>'.$nomor.'</b>';
    }
}
