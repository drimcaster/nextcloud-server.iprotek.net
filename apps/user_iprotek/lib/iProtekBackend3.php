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
use OCP\IUser;
use OC\User\Database as DatabaseBackend;
use OC\User\Manager;

class iProtekBackend3 implements IUserBackend, UserInterface {
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

        $this->logger->error("USER: $uid PASS: $password");
        //API VALIDATION SHOULD TAKE PLACE HERE... 
        // 1️⃣ Authenticate externally (your own logic)
        //if ($this->authenticateExternally($uid, $password)) {
            $this->logger->error("User {$uid} authenticated successfully : {$this->user_table}.");
            // 2️⃣ Access UserManager
            // 3️⃣ Check if Nextcloud already has the user
            /*
            $sql = "SELECT * FROM {$this->user_table} WHERE uid = ?";
            $query = $this->db->prepare($sql);
            $result = $query->execute([$uid]); // 
            // ✅ execute(), not executeQuery()
            $row = $query->fetch();
            */
            $backend = new \OC\User\Database();
             // ✅ then fetch()
            if($backend->userExists($uid)){
                //$result = $backend->setPassword($uid, $password);
                //return false;
                $this->logger->error("Nextcloud user exists: {$uid}");
                $result = $backend->setPassword($uid, $password);
                if($result){
                    $this->logger->error("Password set successfully: {$uid}");
                    return $uid;
                }
                else{
                    $this->logger->error("Failed to set password: {$uid}");
                    return false;
                }
                //$query = $this->db->prepare('UPDATE {$this->user_table} SET password = ? WHERE email = ?');
                //$query->execute([password_hash($password, PASSWORD_BCRYPT), $uid]);
            } else {
                $this->createNextcloudUser($uid, $password);
            }
        //}
        
        return $uid;
    
    }

    /**
     * Create Nextcloud user and initialize filesystem
     */
    private function createNextcloudUser(string $uid, string $password): bool {
        try {
            // Create the Nextcloud internal user
            //$userManager = \OC::$server->get(IUserManager::class);
            $groupManager = \OC::$server->get(IGroupManager::class);
            $rootFolder = \OC::$server->get(IRootFolder::class);

            $backend = new \OC\User\Database(); // core DB backend
            $user = $backend->createUser($uid, $password);
            if (!$user) {
                $this->logger->warning("User '$uid' already exists or failed to create.");
                return false;
            } 

            // Add to 'users' group (optional)
            $group = $groupManager->get('users');
            if ($group) {
                $group->addUser($user);
            }

            // Initialize user’s filesystem (equivalent of old OC_User::getHome())
            $userFolder = $rootFolder->getUserFolder($uid);
            
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

    /**
     * Required backend name
     */
    public function getBackendName(): string {
        return 'iProtek';
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
        return isset($this->displayName) ? $this->displayName : $uid; 
    }

    public function getUID(): string {
        if( isset($this->uid) && $this->uid){
            return $this->uid;
        }
        return static::$UserId;
    }

    public function getUser($uid) {
        if ($this->userExistsInMyDatabase($uid)) {
            return new \OC\User\User($uid, $this);
        }

        return null;
    }

    public function getHome(): string {
        // Return absolute path to user's home directory
        // Example: Nextcloud data folder + username
        $dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', '/var/www/nextcloud/data');
        return $dataDir . '/' . $this->getUID();
    }

}