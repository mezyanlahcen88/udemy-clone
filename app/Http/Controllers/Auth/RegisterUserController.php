<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use Illuminate\Http\Request;
use App\Data\Auth\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Validation\Rules\Password;

class RegisterUserController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RegisterUserData $data)
    {
       $user = RegisterUserAction::run($data);
       return new UserResource($user);
    }

}
