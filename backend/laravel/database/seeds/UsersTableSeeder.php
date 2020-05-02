<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'name'              => 'user',
            'email'             => 'user@example.com',
            'password'          => Hash::make('user'),
            'remember_token'    => Str::random(10),
        ]);
    }
}
