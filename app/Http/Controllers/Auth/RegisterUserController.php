<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Data\Auth\RegisterUserData;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rules\Password;

class RegisterUserController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RegisterUserData $data)
    {

       return $data->toArray();
    }

}
