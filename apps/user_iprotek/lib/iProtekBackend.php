<?php
namespace OCA\UserIprotek;

use OCP\IUserBackend;
use OCP\UserInterface;
use OCP\Util;
use Psr\Log\LoggerInterface;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Files\IRootFolder;
use OC\User\Database as DatabaseBackend;
use OCP\IUser;
use OCA\UserIprotek\AppInfo\PayHttp;

class iProtekBackend extends DatabaseBackend  { //IUserBackend, UserInterface {
    private IDBConnection $db;
    public LoggerInterface $logger;
    public static string $UserId = '';
    private string $user_table;
    private PayHttp $payHttp;
    
    public function __construct(IDBConnection $db, LoggerInterface $logger) {
        parent::__construct();
        $this->db = $db;
        $this->logger = $logger;
        $this->user_table = \OC::$server->getConfig()->getSystemValue('dbtableprefix'). 'users';
        $this->payHttp = new PayHttp();
    }

    /**
     * Check if a user exists
     */
    public function userExists($uid): bool {
        //ALWAYS SET TRUE DUE TO EXTERNAL AUTHENTICATION
        return true;
    }
        

    /**
     * Authenticate the user
     * return false if authentication fails
     * return uid if authentication succeeds
     */
    public function checkPassword($uid, $password) {
        
        $this->logger->error("USER: $uid PASS: $password");
        $this->logger->error("User {$uid} authenticated successfully : {$this->user_table}."); 

        $email = $uid;
        $uid = $this->toValidFolderName($uid);
        
        $payHttp = $this->payHttp;

        //VALIDATION CHECK
        if( !($payHttp->config && isset($payHttp->config["is_enabled"]) ))
        {
            return false;
        }

        if($payHttp->config && $payHttp->config["is_enabled"] !== true){
            
            
            return parent::checkPassword($uid, $password);
        }

        /**
         * API CHECK SHOULD TAKE PLACE HERE
         */

        $client = $payHttp->client();
        
        $data =  ["email"=>$uid, "password"=>$password]; 
        
        try{

            $response = $client->post('login', [
                "json" => $data
            ]);
            
            $response_code = $response->getStatusCode();
            
            $this->logger->error("Authenticate ID: { $response_code}."); 

        }catch(\Exception $ex){
            $this->logger->error($ex->getMessage()); 
            return false;
        }
        return false;
        if($response_code != 200 && $response_code != 201){
            
            return redirect()->back()->with('error', 'Credential Error')->withErrors([ 
                'email' => 'User credential doesn\'t match.'
            ])->withInput($request->only('email'));
            //return [ "status"=>0, "message" => "Invalidated:".$response->getReasonPhrase().$response->getBody(), "status_code"=>$response_code ];
        }
        $result = json_decode($response->getBody(), true);
        $access_token = $result['access_token'];



        $backend = new \OC\User\Database();


        if($backend->userExists($uid)){ 
            $this->logger->error("Nextcloud user exists: {$uid}");
            //UPDATE PASSWORD FOR AUTHENTICATED FOR NEXTCLOUD PURPOSES
            $result = $this->setPassword($uid, $password);
            if($result){
                $this->logger->error("Password set successfully: {$uid}");
                return $uid;
            }
            else{
                $this->logger->error("Failed to set password: {$uid}");
                return false;
            }
        } else {
            //CREATE USER IF NOT EXISTS
            $this->createNextcloudUser($uid, $password, $email);
        } 
        
        return false;
    
    }
    

    /**
     * Create Nextcloud user and initialize filesystem
     */
    private function createNextcloudUser(string $uid, string $password, string $displayName = ""): bool {
        try {
            //CONFIGURATION 
            $config = $GLOBALS['USER_IPROTEK_CONFIG'] ?? [];
            $quota = $config['default_quota'] ?? '5 GB';
           
           
            $groupManager = \OC::$server->get(IGroupManager::class);
            $rootFolder = \OC::$server->get(IRootFolder::class);

            $backend = new \OC\User\Database(); // core DB backend
            $user = $backend->createUser($uid, $password);
            if (!$user) {
                $this->logger->warning("User '$uid' already exists or failed to create.");
                return false;
            } 

            $userManager = \OC::$server->getUserManager();
            $quota = $GLOBALS['default_quota'] ?: '5 GB';
            $user = $userManager->get($uid);
            $user->setQuota($quota);
            $user->setDisplayName($displayName);

            // Add to 'users' group (optional)
            $group = $groupManager->get('users');
            if ($group) {
                $group->addUser($user);
            }

            // Initialize user’s filesystem (equivalent of old OC_User::getHome())
            $userFolder = $rootFolder->getUserFolder($uid );
            
            $homePath = $userFolder->getPath();
            if (!is_dir($homePath)) {
                mkdir($homePath, 0755, true);
            }

            // Trigger post-creation hooks (like dashboard, files, etc.)
            $this->logger->info("Created Nextcloud user '$uid' with home folder '$homePath'");

            return true;
        } catch (\Throwable $e) {
            $this->logger->error("createNextcloudUser() error: " . $e->getMessage());
            return false;
        }
    } 

    public function getRealUID($uid):string{
        $query = $this->db->prepare('SELECT uid FROM '.$this->user_table.' WHERE uid_lower = ?');
        $query->execute([mb_strtolower($uid)]);
        $row = $query->fetch();

        if ( $row && $row !== false) {
            return $row['uid'];
        }

        // Fallback if not found
        return $uid;
    }

    /**
     * Required backend name
     */
    public function getBackendName(): string {
        return 'iProtek';
    }
    
    /**
     * Required backend name
     */
    /*
    public function getHome(string $uid): string {
        // Return absolute path to user's home directory
        // Example: Nextcloud data folder + username
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/nextcloud/data');

        //SET  THE UID TO THE REAL CASE-SENSITIVE VALUE AND TRANSFORM ILLEGAL CHARACTERS INTO VALID FOLDER NAME
        //TRANSFOMR INTO _ FOR ILLEGAL CHARACTERS
        $folder_name = $this->toValidFolderName( $this->getRealUID($uid) );
        return $dataDir . '/' . $folder_name;
    }
        */

    public function toValidFolderName($name) {
        // Replace any character that is NOT a-z, A-Z, 0-9, underscore, or dash with underscore
        $valid = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);

        // Optionally, trim multiple underscores to one
        $valid = preg_replace('/_+/', '_', $valid);

        // Trim underscores from start and end
        $valid = trim($valid, '_');

        return $valid;
    }

}