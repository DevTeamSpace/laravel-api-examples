<?php namespace App\Http\Controllers;

use Request;
use Aloha\Twilio\TwilioInterface;
use Log;

class TwilioController extends Controller
{
    private $twilio;

    /**
     * Create a new controller instance.
     * @param TwilioInterface $twilio
     */
    public function __construct(TwilioInterface $twilio)
    {
        $this->twilio = $twilio;
    }

    public function call()
    {
        $input = \Input::all();
        $code = rand(100000, 999999);
        $phone = $input['mobilePhone'];

        if (!strpos($phone, '+')) {
            $phone = "+" . $phone;
        }
        \DB::table('numbers')->where('phone', $phone)->delete();

        try {
            $this->twilio->message($phone, "Hi " . $input['firstName']
                . ", thanks for choosing Australasian Trading Management! Your verification code is: $code");
        } catch (Exception $e) {
            Log::error('CODE SENDING FAILED', ['Input' => $input]);
            return response()->json(['status' => 'error', 'message' => 'Error starting phone call: ' . $e->getMessage()], 200);

        }
        \DB::table('numbers')->insert(
            ['phone' => $phone, 'code' => $code]
        );
        return response()->json(['status' => 'ok'], 200);
    }

    public function check(){
        $input = Request::all();
        $code = (int)Request::get('code');
        $phone = Request::get('mobilePhone');
        if (!strpos($phone, '+')) {
            $phone = "+" . $phone;
        }
        $number = \DB::table('numbers')->where('phone', $phone)->first();
        if(!$number){
            return response()->json(['status' => 'error', 'message' => 'There is no such a phone number: ' . $phone], 200);
        }
        if((int)$code === (int)$number->code){
            \DB::table('numbers')->where('phone', $phone)->update(['verified' => 1]);
            return response()->json(['status' => 'ok'], 200);
        }
        Log::error('INCORRECT CODE ENTERED', ['Input' => $input]);
        return response()->json(['status' => 'error', 'message' => 'Code not valid. Please try again'], 200);
    }
}