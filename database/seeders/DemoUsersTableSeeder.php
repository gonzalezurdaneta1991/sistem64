<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        \DB::table('users')->delete();

        \DB::table('users')->insert([
            0 => [
                'id' => 1,
                'name' => 'Super Admin',
                'email' => 'superadmin@acculance.com',
                'email_verified_at' => '2022-04-30 22:13:36',
                'password' => Hash::make('acculance2024'),
                'remember_token' => null,
                'account_role' => 1,
                'is_active' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            1 => [
                'id' => 2,
                'name' => 'Mari Johns',
                'email' => 'sales@acculance.com',
                'email_verified_at' => null,
                'password' => Hash::make('acculance2024'),
                'remember_token' => null,
                'account_role' => 0,
                'is_active' => 1,
                'created_at' => '2022-05-14 14:14:40',
                'updated_at' => '2022-05-14 14:14:40',
            ],
            2 => [
                'id' => 3,
                'name' => 'Paki Wolf',
                'email' => 'manager@acculance.com',
                'email_verified_at' => null,
                'password' => Hash::make('acculance2024'),
                'remember_token' => null,
                'account_role' => 0,
                'is_active' => 1,
                'created_at' => '2022-05-15 10:28:43',
                'updated_at' => '2022-05-15 10:28:43',
            ],
            3 => [
                'id' => 4,
                'name' => 'Alok',
                'email' => 'developer@acculance.com',
                'email_verified_at' => null,
                'password' => Hash::make('acculance2024'),
                'remember_token' => null,
                'account_role' => 1,
                'is_active' => 1,
                'created_at' => '2022-05-15 09:14:11',
                'updated_at' => '2022-05-15 09:14:11',
            ],
        ]);
    }
}
