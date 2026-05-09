<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Enums;
enum InventoryTrackType: string
{
    case None = 'NONE';
    case FinishedGood = 'FINISHED_GOOD';
    case RawMaterial = 'RAW_MATERIAL';
    case Both = 'BOTH';
}
