<?php

namespace App\Enums;

enum CourierStatus: string
{
    case InTransit = 'in_transit';
    case Returning = 'returning';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case RiderAssigned = 'rider_assigned';
}
