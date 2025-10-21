<?php
namespace OCA\UserIprotek;

use OCP\IUserBackend;
use OCP\UserInterface;
use OCP\Util;
use Psr\Log\LoggerInterface;
use OCP\IDBConnection;

class iProtekBackend implements IUserBackend, UserInterface {
    private IDBConnection $db;
    public LoggerInterface $logger;
    public static string $UserId = '';
    private string $user_table;
    
    public function __construct(IDBConnection $db, LoggerInterface $logger) {
        $this->db = $db;
        $this->logger = $logger;
        $this->user_table = \OC::$server->getConfig()->getSystemValue('dbtableprefix'). 'users';
    }


    // Optional: a simple in-memory user list
    private array $users = [
        'drimcaster' => [
            'password' => 'secret', // for real use: store hashed passwords
            'displayName' => 'Test User'
        ]
    ];

    /**
     * Check if a user exists
     */
    public function userExists($uid): bool {

        //IF USERID Exists then assign. 

        if(!is_numeric($uid)){ 
            static::$UserId = $uid;
        } 

        //THEN THIS SHOULD RETURN TRUE ALWAYS FOR DUE TO EXTERNAL AUTHENTICATION

        return true;
    }

    /**
     * Authenticate the user
     */
    public function checkPassword($uid, $password): bool {

        //die('checkPassword ' . $uid . ' ' . $password);
        $this->logger->error("USER: $uid PASS: $password");
        
        //API VALIDATION SHOULD TAKE PLACE HERE... 
        // 1️⃣ Authenticate externally (your own logic)
        //if ($this->authenticateExternally($uid, $password)) {
            $this->logger->error("User {$uid} authenticated successfully : {$this->user_table}.");

            // 2️⃣ Access UserManager

            // 3️⃣ Check if Nextcloud already has the user
            $sql = "SELECT * FROM {$this->user_table} WHERE uid = ?";
            $query = $this->db->prepare($sql);
            $result = $query->execute([$uid]); // ✅ execute(), not executeQuery()

            $row = $query->fetch(); // ✅ then fetch()
            if($row){
                $this->logger->error("Nextcloud user exists: {$uid}");
            } else {
                $this->createNextcloudUser($uid, "api-login");
                // Create the user in Nextcloud (no password, since backend handles it)
                /*
                $this->logger->error("Created Nextcloud user: {$uid}");
                
                $backend = new \OC\User\Database(); // ✅ core DB backend (NC33)
                $backend->createUser($uid, "api-login");
                */
                //$databaseBackend->createUser($uid, null);
                
                //$userManager = \OC::$server->getUserManager();
                //$user = $userManager->createUser($uid, null);
            }
            //$this->logger->error("Checking if Nextcloud user exists: {$uid}", ["exists"=>$userManager->userExists($uid)]);
            //$backend = new \OCA\UserDatabase\Backend();
            //$userManager = \OC::$server->getUserManager();
            //$userManager->registerBackend($backend);
            //$this->logger->error("Checking if Nextcloud user exists: {$uid}", ["exists"=>$userManager->userExists($uid)]);

            /*
                if (!$userManager->userExists($uid)) {
                    // 4️⃣ Create the user in Nextcloud (no password, since backend handles it)
                    $user = $userManager->createUser($uid, null);
                    $this->logger->error("Created Nextcloud user: {$uid}");
                }
            */

            // 5️⃣ Return true so NC proceeds with login
        return $uid;
        //}

        // 6️⃣ Invalid credentials
        $logger->warning("Failed login for {$uid}");


        return true;
        if (!isset($this->users[$uid])) {
            return false;
        }

        // For real usage, use password_verify() with hashed password
        $isValid = $this->users[$uid]['password'] === $password;

        $logger->debug("Password valid for $uid: " . ($isValid ? 'yes' : 'no'));
        return $isValid;
    }

    /**
     * Create Nextcloud user and initialize filesystem
     */
    private function createNextcloudUser(string $uid, string $password): bool {
        try {
            // Create the Nextcloud internal user
            $backend = new \OC\User\Database(); // core DB backend
            $user = $backend->createUser($uid, $password);
            if (!$user) {
                $this->logger->warning("User '$uid' already exists or failed to create.");
                return false;
            }

            // Initialize filesystem
            \OC_Util::setupFS($uid);
            $home = \OC_User::getHome($uid);
            if (!is_dir($home)) {
                mkdir($home, 0755, true);
            }

            // Trigger post-creation hooks (like dashboard, files, etc.)
            \OC_Hook::emit('OC_User', 'post_createUser', ['uid' => $uid]);

            $this->logger->info("Created Nextcloud user '$uid' with filesystem at '$home'");
            return true;
        } catch (\Throwable $e) {
            $this->logger->error("createNextcloudUser() error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Required backend name
     */
    public function getBackendName(): string {
        return 'iprotek';
    }

    /**
     * Optional: search users for listings
     */
    public function searchUsers($search, $limit = 10, $offset = 0): array {
        $results = [];
        foreach ($this->users as $uid => $info) {
            if (stripos($uid, $search) !== false || stripos($info['displayName'], $search) !== false) {
                $results[] = $uid;
            }
        }
        return array_slice($results, $offset, $limit);
    }

    /**
     * Optional: return display names
     */
    public function getDisplayNames($search = '', $limit = null, $offset = null): array {
        $results = [];
        foreach ($this->users as $uid => $info) {
            if ($search === '' || stripos($uid, $search) !== false || stripos($info['displayName'], $search) !== false) {
                $results[$uid] = $info['displayName'];
            }
        }
        if ($offset !== null && $limit !== null) {
            $results = array_slice($results, $offset, $limit, true);
        }
        return $results;
    }

    /**
     * Optional: indicate this backend provides listings
     */
    public function hasUserListings(): bool {
        return true;
    }

    /**
     * Optional: indicate supported actions
     */
    public function implementsActions($actionName): bool {
        $uid = static::$UserId;
        $this->logger->error("Checking for user : $uid ACTION: $actionName");
        return true;
    }

    public function deleteUser($uid): bool { return false; }

    public function getUsers($search = '', $limit = null, $offset = null): array { 
        return [$this->uid]; 
    }

    public function getDisplayName($uid): string {  
        return $this->displayName; 
    }

    public function getUID(): string {
        if( isset($this->uid) && $this->uid){
            return $this->uid;
        }
        

        return static::$UserId;
    }


    public function getHome(): string {
        // Return absolute path to user's home directory
        // Example: Nextcloud data folder + username
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/nextcloud/data');
        return $dataDir . '/' . $this->getUID();
    }

}