<?php

namespace App\Enums;

enum FileChangesEnum
{
    case CREATED;
    case MODIFIED;
    case DELETED;
}
