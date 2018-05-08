<?php

namespace models\channels;


use PDO;
use models\ModelDB as MDB;
use controllers\channels\ChannelHelperController as CHC;

class DBInventory
{

    public static function getEtailInventory()
    {

        $sql = "SELECT * FROM etail_inventory";

        return MDB::query($sql, [], 'fetchAll', PDO::FETCH_ASSOC);

    }

    public static function updateEtailInventory($valuesArray)
    {

        $values = "";
        $queryParameters = [];
        $sql = "INSERT INTO etail_inventory (loc, sku, qty) VALUES ";
        foreach($valuesArray as $key => $valueArray) {
            $location = ":loc$key";
            $sku = ":sku$key";
            $qty = ":qty$key";
            $values .= "(";
            $values .= "$location,";
            $values .= "$sku,";
            $values .= "$qty";
            for($x = 0; $x < count($valueArray); $x++) {

                switch($x) {
                    case 0:
                        $queryParameters[$location] = $valueArray["LOC"];
                        break;
                    case 1:
                        $queryParameters[$sku] = $valueArray["SKU"];
                        break;
                    case 2:
                        $queryParameters[$qty] = $valueArray["QTY"];
                        break;
                }

                // $values .= $value === end($valueArray) ? trim($value) : trim($value) . ",";
            }
            $values .= $valueArray === end($valuesArray) ? ")" : "),";
        }
        $sql .= $values;
        $sql .= " ON DUPLICATE KEY UPDATE ";
        $sql .= "qty = VALUES(qty)";
        // echo "$sql<br><br>";
        $queryParams = [
            ":sku" => $sku,
            ":qty" => $qty,
            ":qty2" => $qty
        ];
        // print_r($queryParams);
        // echo "<br><br>";
        // print_r($queryParameters);
        return MDB::query($sql, $queryParameters, 'id');

    }

    public function getUpdatedInventory($interval)
    {

        $sql = "SELECT loc, sku, qty FROM etail_inventory tb";

        if ($interval) {
            $sql .= " WHERE tb.last_updated >= DATE_SUB(NOW(), INTERVAL $interval MINUTE)";
        }

        return MDB::query($sql, [], 'fetchAll', PDO::FETCH_ASSOC);

    }

}