<?php declare(strict_types=1);

namespace Tolkam\Layers\Base\Infrastructure\Store\Doctrine\Helper;

use Tolkam\Layers\Base\Domain\Entity\SnapshotInterface;
use Tolkam\Layers\Base\Domain\Value\Common\BooleanValue;
use Tolkam\Layers\Base\Domain\Value\Common\Time;

class Snapshot
{
    /**
     * Converts snapshot to MySQL array
     *
     * @param SnapshotInterface $snapshot
     *
     * @return array
     */
    public static function toArray(SnapshotInterface $snapshot): array
    {
        $snapshot = $snapshot->toArray(false);
        
        foreach ($snapshot as $k => $value) {
            if ($value instanceof Time) {
                $value = $value->toMysqlDatetime();
            }
            elseif ($value instanceof BooleanValue) {
                $value = $value->value() ? '1' : '0';
            }
            elseif ($value instanceof SnapshotInterface) {
                $value = self::toArray($value);
            }
            else {
                $value = !is_null($value) ? (string) $value : $value;
            }
            
            $snapshot[$k] = $value;
        }
        
        return $snapshot;
    }
}
