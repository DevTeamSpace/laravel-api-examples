<?php namespace App\Http\Controllers;

use App\Http\Requests\SubscribeRequest;
use Auth;
use App\Models\User;
use Request;
use App\Models\Note;

class PayPalController extends Controller
{

    public function thanks()
    {
        if (isset($_GET['tx']) && ($_GET['tx']) != null && ($_GET['tx']) != "") {
            $tx = $_GET['tx']; //transaction ID
            $identity = 'key here';
            $ch = curl_init();
            $url = 'https://www.paypal.com/cgi-bin/webscr';
            $fields = array(
                'cmd' => '_notify-synch',
                'tx' => $tx,
                'at' => $identity,
            );

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_USERAGENT, 'cURL/PHP');
            $res = curl_exec($ch);             
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $subscription = "empty";
            if (!$res) {
                //HTTP ERROR
                return redirect('/sorry');
            } else {
                // parse the data
                $lines = explode("\n", $res);
                $keyarray = array();
                if (strcmp($lines[0], "SUCCESS") == 0) {
                    for ($i = 1; $i < count($lines) - 1; $i++) { //last string is empty
                        list($key, $val) = explode("=", $lines[$i]);
                        $keyarray[urldecode($key)] = urldecode($val);
                    }
                    $user = User::find(me()->id);
                    $user->transaction_subject = $keyarray['item_name'];
                    if ($keyarray['item_name'] === 'Trial') {
                        if ($user->payer_email) {
                            return redirect('/auth/login');
                        }
                        $user->status = 'trial';
                    } else {
                        $user->status = 'full';
                    }
                    $subscription = $keyarray['item_name'];
                    $user->payer_email = $keyarray['payer_email'];
                    $date = new \DateTime($keyarray['payment_date']);
                    $date->setTimezone(new \DateTimezone('Pacific/Auckland'));
                    $user->payment_date = $date->format('Y-m-d H:i:s');
                    $user->isOnline = 'Yes';
                    $user->sessionId = \Session::getId();
                    $user->ip = Request::ip();
                    if ($user->save()) {
                        Auth::setUser($user);
                        $data = [
                            'email' => $user->email,
                            'name' => $user->firstName,
                        ];
                        $this->sendThanks($data);
                    } else {
                        return redirect('/sorry');
                    }
                } else if (strcmp($lines[0], "FAIL") == 0) {
                    // show error message - failed transaction
                    return redirect('/sorry');
                }
            }
            curl_close($ch);
        } else {
            //show error message - no transaction code received
            return redirect('/sorry');
        }
        return view('paypal.thanks')->with('subscription', $subscription);
    }
	//---------------------------------- OTHER METHODS HERE ----------------------------------
}