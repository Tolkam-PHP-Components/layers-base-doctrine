<?php declare(strict_types=1);

namespace Tolkam\Layers\Base\Infrastructure\Store\Doctrine\Helper;

class Aliases
{
    /**
     * @var string
     */
    private static string $glue = '.';
    
    /**
     * Converts multidimensional array into namespaced column names
     *
     * @param array $arr
     *
     * @return array
     */
    public static function arrayToColumns(array $arr): array
    {
        $glue = self::$glue;
        $columns = [];
        
        foreach ($arr as $alias => $cols) {
            foreach ($cols as $col) {
                if (is_array($col)) {
                    $colName = key($col);
                    $aliasName = "\"{$alias}{$glue}{$col[$colName]}\"";
                }
                else {
                    $colName = "{$alias}{$glue}`{$col}`";
                    $aliasName = "\"{$alias}{$glue}{$col}\"";
                }
                
                $columns[] = "$colName $aliasName";
            }
        }
        
        return $columns;
    }
    
    /**
     * Converts namespaced results to multidimensional array
     *
     * @param array       $row
     * @param string|null $mergeInto
     *
     * @return array
     */
    public static function rowToArrays(
        array $row,
        string $mergeInto = null
    ): array {
        
        $glue = self::$glue;
        $arr = [];
        
        foreach ($row as $name => $value) {
            if (strpos($name, $glue) !== false) {
                [$alias, $name] = explode($glue, $name);
                $arr[$alias][$name] = $value;
            }
        }
        
        if ($mergeInto !== null) {
            foreach ($arr as $k => $v) {
                if ($k !== $mergeInto) {
                    $arr[$mergeInto][$k] = $v;
                    unset($arr[$k]);
                }
            }
            
            $arr = $arr[$mergeInto];
        }
        
        return $arr;
    }
}
