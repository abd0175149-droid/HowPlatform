<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesUsersController extends Controller
{
    public function index(Request $request)
    {
        // تحقق من المفتاح السري
        $authHeader = $request->header('Authorization');
        $secretKey  = config('services.auth.secret_key'); 
        
        if (!$authHeader || $authHeader !== 'Bearer ' . $secretKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }


        // جلب جميع المستخدمين الذين is_sales = 1
        $users = DB::table('users')
            ->where('is_sales', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }
}
