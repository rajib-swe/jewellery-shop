<?php

namespace App;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Salary = 'salary';
    case Electricity = 'electricity';
    case Transport = 'transport';
    case Maintenance = 'maintenance';
    case Purchase = 'purchase';
    case Tax = 'tax';
    case Other = 'other';
}
