<?php
namespace OCA\UserIprotek\AppInfo;

if (class_exists(__NAMESPACE__ . '\\PayHttp')) {
    return;
}
class PayHttp
{

    public $config = [];
    public $headers = [];

    public function __construct() {

        // Load your custom config file
        $configPath = __DIR__ . '/../../config/config.php';
        if (file_exists($configPath)) {
            //$GLOBALS['USER_IPROTEK_CONFIG'] = include $configPath;
            $this->config = include $configPath;
        }
    }
    
    public function http2($is_auth = true, $access_token="", $headers=null){

        $config = $this->config;

        $pay_url = $config['iprotek_api_url'];
        $client_id = $config['client_id'];
        $client_secret = $config['client_secret']; 

        if(!$headers){
            $headers = [];
            if($is_auth == false){
                $headers = [
                    "Accept"=>"application/json",
                    "Content-Type"=>"application/json",
                    "Authorization"=>"Bearer ".$client_id.":".$client_secret
                ];
            }
            else{
                $headers = [
                    "Accept"=>"application/json",
                    "Content-Type"=>"application/json",
                    "Authorization"=>"Bearer ".$access_token
                ];
            }
        }

        //ADD FILTER HEADER
        $headers["CLIENT-ID"] = $client_id;
        $headers["SOURCE-URL"] = $config["app_url"]; 
        $headers["SOURCE-NAME"] = $config["app_name"]; 
        $headers["SOURCE-TYPE"] = $config["app_type"];
        $headers["SYSTEM-ID"] = $config["system_id"]; 
        $headers["SYSTEM-URL"] = $config["system_url"];
        $headers["PAY-USER-ACCOUNT-ID"] = 0;
        $headers["PAY-PROXY-ID"] = 0;

        $this->headers = $headers;
        
        $client = new \GuzzleHttp\Client([
            'base_uri' => $pay_url,
            "http_errors"=>false, 
            "verify"=>false, 
            "curl"=>[
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Specify HTTP/2
            ],
            "headers"=>$headers
         ]);
        return $client;
    }

    public function client(){
        return $this->http2(false);
    }

    public function auth($access_token){
        return $this->http2(true, $access_token);
    }

    public function get_client( $url, $headers = null){
        $client = $this->http2(true, "", $headers);
        return $client->get($url);
    }
    

    public function send_reconvery_link($email){
        
        $data = [
            "email"=>$email,
            "redirect_url"=> $this->config["app_url"],
        ];
        $client = $this->client();
        $response = $client->post('send-recovery', [
            "json" => $data
        ]);
        $response_code = $response->getStatusCode();
        if($response_code != 200 && $response_code != 201){
            
            return [ "status"=>0, "message" => "Invalidated:(".$response_code.")".$response->getReasonPhrase(), "status_code"=>$response_code ];
        }
        $result = json_decode($response->getBody(), true);

        return $result;
    }

    public function client_info(){
        
        $client = $this->client();
        
        $response = $client->get('client-info');
        
        $response_code = $response->getStatusCode(); 
        if($response_code != 200 && $response_code != 201){
            return null;
        }
        $result = json_decode($response->getBody(), true);
        return $result;
    }

    public function get_client_load($url){
        $config = $this->config;

        $client_id =  $config["client_id"];
        $client_secret = $config["client_secret"];
        $pay_url = $config["iprotek_api_url"];
        
        $client = new \GuzzleHttp\Client([
            'base_uri' => $url,
            "http_errors"=>false, 
            "verify"=>false, 
            "curl"=>[
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Specify HTTP/2
            ],
            "headers"=>[
                "Accept"=>"application/json",
                "CLIENT-ID"=>$client_id,
                "SECRET"=>$client_secret,
                "PAY-URL"=>$pay_url
            ]
         ]);
         $response = $client->get('');
         $response_code = $response->getStatusCode(); 
         if($response_code != 200 && $response_code != 201){
             return json_decode($response->getBody(), true);
         }
         $result = json_decode($response->getBody(), true);
         return $result;
    }

    public function pusher_info(){
        $client_info = $this->client_info(); 
        if($client_info){

            if(is_array($client_info)){
                $socket_settings = isset( $client_info['socket_settings'] ) ?  $client_info['socket_settings'] : null;
                if($socket_settings){
                    $socket_settings = json_decode( json_encode($socket_settings), TRUE);
                } 
                return $socket_settings;
            }
            else{
                if(isset( $client_info->socket_settings ) ){
                    $socket_settings = json_decode( json_encode($client_info->socket_settings), TRUE);
                    return $socket_settings;
                } 
            }
        }
        return null;
    }

    public function send_pusher_notification($channel, $bind_trigger, $data=[]){
        $pusher_info = $this->pusher_info();

        if($pusher_info && $pusher_info['is_active'] && $pusher_info['socket_name'] == 'PUSHER.COM'){


            $cluster = isset($pusher_info['cluster']) ? $pusher_info['cluster']:"";
            $key = isset($pusher_info['key']) ? $pusher_info['key']:"";
            $secret = isset($pusher_info['secret']) ? $pusher_info['secret']:"";
            $app_id = isset($pusher_info['app_id']) ? $pusher_info['app_id']:"";  
            
            $options = array(
                'cluster' => $cluster,//'ap1', //cluster 
                'useTLS' => false
            );
            $pusher = new \Pusher\Pusher(
                $key,//'3ba4f1b9531904744a8e', //key
                $secret, //'1b7dd30d6604966641ab', //secret
                $app_id, //'1858123', //app_id
                $options
            );
            
            //$data['message'] = 'new sms';
            $pusher->trigger($channel, $bind_trigger, $data);


        }

    }

    public function get_client_users($email){
        
        $client = $this->client();

        $queryString = "";        
        if($email)
            $queryString = http_build_query(["search"=>$email, "exact"=>"email"]);
        
        $response = $client->get('client-users?'.$queryString);
        
        $response_code = $response->getStatusCode();
        
        $result = json_decode($response->getBody(), true); 
        
        if($response_code != 200 && $response_code != 201){
            return null;
        }

        return $result;
    }



}
