<?php

namespace Danestves\LaravelPolar\Tests\Fixtures;

use Danestves\LaravelPolar\Billable;
use Danestves\LaravelPolar\Contracts\Billable as BillableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tests\Fixtures\Factories\UserFactory;

class User extends Model implements BillableContract
{
    use Billable;
    use HasFactory;

    protected $guarded = [];

    public function getKey()
    {
        return 'user_123';
    }

    public function getMorphClass()
    {
        return 'users';
    }

    protected static function newFactory()
    {
        return new UserFactory;
    }
}
