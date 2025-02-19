<?php

namespace App\Support;

use DateTimeInterface;

trait ModelDatesSerializedAsTimestamp
{

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Uv');
    }

}
