<?php

namespace Test\Model;

use Core\Database\DB;
use Core\Model\Query;
use Core\Valid\Hash;
use Test\ApplicationTest as TestCase;

class DBCRUDTest extends TestCase
{
    public function testInsert()
    {
        DB::table('users')->delete();

        $data = DB::table('users')
            ->create([
                'nama' => 'user',
                'email' => 'user@example.com',
                'password' => Hash::make('password')
            ])
            ->toArray();

        $this->assertArrayHasKey('nama', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('password', $data);

        DB::table('users')->delete();
    }

    public function testDBMustBeReturnQuery()
    {
        $model = DB::table('users')->limit(5);

        $this->assertTrue($model instanceof Query);
    }
}
