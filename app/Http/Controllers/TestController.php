<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class TestController extends Controller
{
    public function postcodes($postcodes)
    {
        $postcodes_result =  DB::table('postcodes')->where('postcode', 'like', $postcodes . '%')->get();
        if ($postcodes_result->isNotEmpty()) {
            return response()->json($postcodes_result, 200);
        } else {
            return response()->json('Resource not found', 404);
        }
    }

    public function busStops($postcode, $filter = null)
    {
        if ($filter == 'bus') {
            $postcodes_result =  DB::table('postcodes')->where('id', $postcode)->first();
            $bus_stops = DB::table('busstops')->select(DB::raw('*, ( 6367 * acos( cos( radians(' . $postcodes_result->latitude . ') ) * cos( radians( lat ) ) * cos( radians( lon ) - radians(' . $postcodes_result->longitude . ') ) + sin( radians(' . $postcodes_result->latitude . ') ) * sin( radians( lat ) ) ) ) AS distance'))
                ->orderBy('distance')
                ->limit(5)
                ->get();
            return response()->json($bus_stops, 200);
        }
        if ($filter == 'school') {
            $postcodes_result =  DB::table('postcodes')->where('id', $postcode)->first();
            $postcodes_result = DB::table('postcodes')->select(DB::raw('*, ( 6367 * acos( cos( radians(' . $postcodes_result->latitude . ') ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(' . $postcodes_result->longitude . ') ) + sin( radians(' . $postcodes_result->latitude . ') ) * sin( radians( latitude ) ) ) ) AS distance'))
                ->having('distance', '<', 10)
                ->orderBy('distance')
                ->get()->toArray();
            $postcodes_result = array_column($postcodes_result, 'id');
            $schools =  DB::table('schools')->whereIn('postcode_id', $postcodes_result)->get();
            return response()->json($schools, 200);
        }
        if ($filter == 'address') {
            $addresses = DB::table('addresses')
                ->leftJoin('postcodes', 'postcodes.id', '=', 'addresses.postcode_id')
                ->where('addresses.postcode_id', $postcode)
                ->get();
            return response()->json($addresses, 200);
            dd($addresses);
        }
        return response()->json('Resource not found', 404);
    }

    public function report($pdf = null)
    {
        $user_id = 6242;
        $user_info = DB::table('users')->select('*', 'houses.id as houses_id')
            ->leftJoin('houses', 'houses.user_id', '=', 'users.id')
            ->leftJoin('addresses', 'addresses.id', '=', 'houses.address_id')
            ->leftJoin('postcodes', 'postcodes.id', '=', 'houses.postcode_id')
            ->where('users.id', $user_id)
            ->get();
        if ($user_info[0]->propertytype == 1) {
            $user_info[0]->propertytype = 'FLAT';
        } else if ($user_info[0]->propertytype == 2) {
            $user_info[0]->propertytype = 'small house';
        } else if ($user_info[0]->propertytype == 3) {
            $user_info[0]->propertytype = 'big house';
        } else if ($user_info[0]->propertytype == 4) {
            $user_info[0]->propertytype = 'Villa';
        } else {
            $user_info[0]->propertytype = '-';
        }
        $user_likes = DB::table('likes')
            ->leftJoin('users', 'users.id', '=', 'likes.a')
            ->where('likes.a', $user_id)
            ->get();
        $user_likes_received = DB::table('likes')
            ->leftJoin('users', 'users.id', '=', 'likes.b')
            ->where('likes.b', $user_id)
            ->count();
        $Match_iD  = DB::table('likes')
            ->where('like', 1)
            ->where(function ($query) use ($user_id) {
                $query->where('b', $user_id)
                    ->orWhere('a', $user_id);
            })
            ->get();
        $Number_of_chats = DB::table('chats')
            ->where(function ($query) use ($user_id) {
                $query->where('from', $user_id)
                    ->orWhere('to', $user_id);
            })
            ->count();
        $Number_of_people  = DB::table('people')
            ->where('user_id', $user_id)
            ->count();
        $Number_of_old_men = DB::table('people')
            ->where('user_id', $user_id)
            ->where('sex', 'M')
            ->where('age', '>', 45)
            ->count();
        $Number_of_matches = DB::table('likes')->where('like', 1)->count();
        $Like_iDs = json_encode(array_column($user_likes->toArray(), 'b'));
        $user_likes_given = count($user_likes);
        $Match_iDs = json_encode(array_diff(array_column($Match_iD->toArray(), 'a'), array_column($Match_iD->toArray(), 'b')));
        $result = [
            'User ID' => $user_info[0]->id,
            'Full Name' => $user_info[0]->name . ' ' . $user_info[0]->surname,
            'House ID' => $user_info[0]->houses_id,
            'Property type' => $user_info[0]->propertytype,
            'Full address' => $user_info[0]->postcode
                . ', ' . $user_info[0]->district
                . ', ' . $user_info[0]->locality
                . ', ' . $user_info[0]->street
                . ', ' . $user_info[0]->site
                . ', ' . $user_info[0]->site_number
                . ', ' . $user_info[0]->site_description
                . ', ' . $user_info[0]->site_subdescription,
            "Number of likes given" => $user_likes_given,
            "Like IDs" => $Like_iDs,
            "Number of likes received" => $user_likes_received,
            "Number of matches" => $Number_of_matches,
            "Match IDs" => $Match_iDs,
            "Number of chats" => $Number_of_chats,
            "Number of people" => $Number_of_people,
            "Number of old men" => $Number_of_old_men,

        ];
        if (empty($pdf)) {
            return response()->json($result, 200);
        } else {
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML('<div>'.json_encode($result).'</div>');
            return $pdf->stream();
        }
    }
}
