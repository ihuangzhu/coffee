<?php
declare(strict_types=1);
namespace App\Modules\Catalog\Enums;
enum StockDeductMode: string
{
    case SaleDeduct = 'SALE_DEDUCT';
    case ManualDeduct = 'MANUAL_DEDUCT';
    case ProductionDeduct = 'PRODUCTION_DEDUCT';
}
