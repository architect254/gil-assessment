<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['code' => 'C0001', 'name' => 'Acme Trading Ltd', 'phone' => '+254700111222', 'email' => 'info@acme.co.ke', 'address' => 'Mombasa Road, Nairobi'],
            ['code' => 'C0002', 'name' => 'Brightways Distributors', 'phone' => '+254711333444', 'email' => 'sales@brightways.co.ke', 'address' => 'Industrial Area, Nairobi'],
            ['code' => 'C0003', 'name' => 'Coast Hardware Supplies', 'phone' => '+254722555666', 'email' => 'info@coasthardware.co.ke', 'address' => 'Nyali Road, Mombasa'],
            ['code' => 'C0004', 'name' => 'Delta Enterprises', 'phone' => '+254733777888', 'email' => 'contact@delta.co.ke', 'address' => 'Kisumu'],
            ['code' => 'C0005', 'name' => 'Evergreen Stores', 'phone' => '+254701999888', 'email' => 'evergreen@stores.co.ke', 'address' => 'Eldoret'],
            ['code' => 'C0006', 'name' => 'Highland Wholesalers', 'phone' => '+254720123456', 'email' => 'sales@highland.co.ke', 'address' => 'Nakuru'],
            ['code' => 'C0007', 'name' => 'Jamia Traders', 'phone' => '+254705654321', 'email' => 'jamia@traders.co.ke', 'address' => 'Eastleigh, Nairobi'],
            ['code' => 'C0008', 'name' => 'Kitale Agrovet Ltd', 'phone' => '+254799888777', 'email' => 'kitale@agrovet.co.ke', 'address' => 'Kitale'],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(['code' => $customer['code']], $customer);
        }
    }
}
