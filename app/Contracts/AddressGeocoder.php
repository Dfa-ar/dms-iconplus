<?php

namespace App\Contracts;

use App\Models\PaOrder;

interface AddressGeocoder
{
    /** @return array{lat:float,lng:float}|null */
    public function geocode(PaOrder $paOrder): ?array;
}