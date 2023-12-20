<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;

class ProductImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Product([
//            '#' => $row[0],
//            'image' => $row[1],
//            'type' => $row[2],
//            'sku' => $row[3],
//            'name' => $row[4],
//            'sell' => $row[5],
//            'category' => $row[6],
//            'qty' => $row[7],
        ]);
    }
}
