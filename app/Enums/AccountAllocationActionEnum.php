<?php

namespace App\Enums;

enum AccountAllocationActionEnum : string
{
    case ALLOCATE = 'allocate';
    case REVERSE = 'reverse';
}
