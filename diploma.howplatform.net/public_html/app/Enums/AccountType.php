<?php
namespace App\Enums;

enum AccountType: int
{
    case ADMIN   = 1;
    case TEACHER = 2;
    case STUDENT = 3;
    case TRIAL = 4; 
}
