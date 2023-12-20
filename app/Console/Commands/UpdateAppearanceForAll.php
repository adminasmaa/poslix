<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;

class UpdateAppearanceForAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-appearance-for-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update appearance for all locations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // update all locations
        $this->info('Updating locations...');
        $locations = Location::all();
        $langs = ['ar', 'en'];
        foreach ($locations as $location) {
            // Initialize an empty JSON object
            $appearance = (object) [];

            foreach ($langs as $lang){
                // update json data
                $appearance->logo = 'https://app.poslix.com/images/logo1.png';
                $appearance->website = 'poslix.com';
                $appearance->instagram = 'instagram.com';
                $appearance->whatsapp = 'whatsapp.com';
                $appearance->{$lang} = (object) [
                    'name' => 'Poslix',
                    'tell' => '09123456789',
                    'txtCustomer' => ($lang == 'ar' ? 'العميل' : 'Customer'),
                    'orderNo' => ($lang == 'ar' ? 'رقم الطلب' : 'Order No'),
                    'txtDate' => ($lang == 'ar' ? 'التاريخ' : 'Date'),
                    'txtQty' => ($lang == 'ar' ? 'الكمية' : 'Qty'),
                    'txtItem' => ($lang == 'ar' ? 'الصنف' : 'Item'),
                    'txtAmount' => ($lang == 'ar' ? 'السعر' : 'Amount'),
                    'txtTax' => ($lang == 'ar' ? 'الضريبة' : 'Tax'),
                    'txtTotal' => ($lang == 'ar' ? 'الاجمالي' : 'Total'),
                    'footer' => ($lang == 'ar' ? 'شكرا' : 'Thanks'),
                    'email' => 'info@poslix.com',
                    'address' => ($lang == 'ar' ? 'العنوان' : 'Address'),
                    'vatNumber' => ($lang == 'ar' ? 'الرقم الضريبي' : 'VAT No'),
                    'customerNumber' => ($lang == 'ar' ? 'رقم العميل' : 'Customer No'),
                    'description' => ($lang == 'ar' ? 'الوصف' : 'Description'),
                    'unitPrice' => ($lang == 'ar' ? 'سعر الوحدة' : 'Unit Price'),
                    'subTotal' => ($lang == 'ar' ? 'المجموع' : 'Sub Total'),
                ];
            }
            $location->invoice_details = json_encode($appearance);
            $location->save();
        }
        $this->info('Done!');
    }
}
