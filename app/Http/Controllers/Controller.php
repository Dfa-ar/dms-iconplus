<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * PENTING: sejak Laravel 11, base Controller bawaan skeleton KOSONG (tidak
 * ada AuthorizesRequests/ValidatesRequests/DispatchesJobs seperti versi
 * lama). Karena semua controller di paket ini memanggil $this->authorize(...)
 * dan semua Form Request memanggil $this->user()->can(...) (itu aman, tidak
 * butuh trait ini) -- tapi $this->authorize() di controller BUTUH trait ini.
 * Tanpa baris "use AuthorizesRequests;" di bawah, semua controller akan
 * Fatal Error "Call to undefined method authorize()".
 */
abstract class Controller
{
    use AuthorizesRequests;
}
