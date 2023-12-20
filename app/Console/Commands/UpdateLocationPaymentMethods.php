<?php

namespace App\Console\Commands;

use App\Models\Location;
use Illuminate\Console\Command;

class UpdateLocationPaymentMethods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-location-payment-methods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update location payment methods';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating location payment methods...');

        $locations = Location::select('id')->get();

        $paymentMethodsArray = ["cash","card","cheque","bank"];

        foreach ($locations as $location) {
            $checkMethod = $location->paymentMethods()->whereIn('name',$paymentMethodsArray)->first();
            if ($checkMethod) {
                $this->info('Location '.$location->id.' already has payment methods');
                continue;
            }
            $this->info('Updating location '.$location->id.' payment methods...');
            $location->paymentMethods()->createMany([
                [
                    'name' => 'cash',
                    'enable_flag' => 1,
                ],
                [
                    'name' => 'card',
                    'enable_flag' => 1,
                ],
                [
                    'name' => 'cheque',
                    'enable_flag' => 1,
                ],
                [
                    'name' => 'bank',
                    'enable_flag' => 1,
                ],
            ]);
        }
        $this->info('Done!');
    }
}
