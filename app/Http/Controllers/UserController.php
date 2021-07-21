<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = User::where('email', '!=', 'equipemarketplace@triibo.com.br')->orderBy('name','ASC')->paginate(5);

        return view('users.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $valData = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|max:255|email|allowed_domain|unique:users,email',
            'password' => 'required|string|min:8|same:password_confirmation',
        ]);

        $valData['password'] = Hash::make($valData['password']);

        $user = User::create($valData);

        return $user;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find($id);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);

        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $valData = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|max:255|email|allowed_domain|'.Rule::unique('users')->ignore($user->id),
            'password' => 'nullable|string|min:8|same:password_confirmation',
        ]);

        $user->name  = $valData['name'];
        $user->email = $valData['email'];

        if (!empty($valData['password'])) $user->password = Hash::make($valData['password']);

        $user->save();

        return $user;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  User  $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        $user->delete();

        return $user;
    }
}
