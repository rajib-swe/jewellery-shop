<?php

namespace App;

enum PawnReminderKind: string
{
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
}
