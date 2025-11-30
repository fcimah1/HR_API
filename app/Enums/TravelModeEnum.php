<?php

namespace App\Enums\Enums;

enum TravelModeEnum: int
{
    case BUS = 1;
    case TRAIN = 2;
    case PLANE = 3;
    case TAXI = 4;
    case RENTAL_CAR = 5;
}
