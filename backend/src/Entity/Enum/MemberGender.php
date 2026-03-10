<?php

namespace App\Entity\Enum;

enum MemberGender: string
{
    case MALE   = 'male';
    case FEMALE = 'female';
    case OTHER  = 'other';
}
