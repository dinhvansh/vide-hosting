<?php

namespace App\Enums;

enum NodeStatus: string
{
    case Active = 'ACTIVE';
    case Draining = 'DRAINING';
    case Maintenance = 'MAINTENANCE';
    case Full = 'FULL';
    case Offline = 'OFFLINE';
    case Disabled = 'DISABLED';
}
