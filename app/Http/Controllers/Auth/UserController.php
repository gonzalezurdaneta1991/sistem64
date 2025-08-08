<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function current(Request $request)
    {
        return new UserResource($request->user());
    }

    public function allUser(){
        $users = User::where('is_active', true)->get();
    
        return AllUserResource::collection($users);
    }
}
