<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * ID 4.1: Method isAdmin() pada class User dengan role admin
     * Input: role = 'admin'
     * Expected Output: true
     */
    public function test_is_admin_returns_true_for_admin_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertTrue($user->isAdmin());
    }
    
    /**
     * ID 4.2: Method isAdmin() pada class User dengan role bukan admin
     * Input: role = 'frontend'
     * Expected Output: false
     */
    public function test_is_admin_returns_false_for_non_admin_role()
    {
        $user = new User();
        $user->role = 'frontend';
        
        $this->assertFalse($user->isAdmin());
    }
    
    /**
     * ID 5.1: Method isAdmin1() pada class User dengan role admin
     * Input: role = 'admin'
     * Expected Output: true
     */
    public function test_is_admin1_returns_true_for_admin_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertTrue($user->isAdmin1());
    }
    
    /**
     * ID 5.2: Method isAdmin1() pada class User dengan role admin1
     * Input: role = 'admin1'
     * Expected Output: true
     */
    public function test_is_admin1_returns_true_for_admin1_role()
    {
        $user = new User();
        $user->role = 'admin1';
        
        $this->assertTrue($user->isAdmin1());
    }
    
    /**
     * ID 5.3: Method isAdmin1() pada class User dengan role bukan admin atau admin1
     * Input: role = 'frontend'
     * Expected Output: false
     */
    public function test_is_admin1_returns_false_for_other_roles()
    {
        $user = new User();
        $user->role = 'frontend';
        
        $this->assertFalse($user->isAdmin1());
    }
    
    /**
     * ID 6.1: Method isAdmin2() pada class User dengan role admin2
     * Input: role = 'admin2'
     * Expected Output: true
     */
    public function test_is_admin2_returns_true_for_admin2_role()
    {
        $user = new User();
        $user->role = 'admin2';
        
        $this->assertTrue($user->isAdmin2());
    }
    
    /**
     * ID 6.2: Method isAdmin2() pada class User dengan role bukan admin2
     * Input: role = 'admin'
     * Expected Output: false
     */
    public function test_is_admin2_returns_false_for_non_admin2_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertFalse($user->isAdmin2());
    }
    
    /**
     * ID 7.1: Method isUser() pada class User dengan role user
     * Input: role = 'user'
     * Expected Output: true
     */
    public function test_is_user_returns_true_for_user_role()
    {
        $user = new User();
        $user->role = 'user';
        
        $this->assertTrue($user->isUser());
    }
    
    /**
     * ID 7.2: Method isUser() pada class User dengan role bukan user
     * Input: role = 'admin'
     * Expected Output: false
     */
    public function test_is_user_returns_false_for_non_user_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertFalse($user->isUser());
    }
    
    /**
     * ID 8.1: Method isFrontend() pada class User dengan role frontend
     * Input: role = 'frontend'
     * Expected Output: true
     */
    public function test_is_frontend_returns_true_for_frontend_role()
    {
        $user = new User();
        $user->role = 'frontend';
        
        $this->assertTrue($user->isFrontend());
    }
    
    /**
     * ID 8.2: Method isFrontend() pada class User dengan role bukan frontend
     * Input: role = 'admin'
     * Expected Output: false
     */
    public function test_is_frontend_returns_false_for_non_frontend_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertFalse($user->isFrontend());
    }
    
    /**
     * ID 9.1: Method isBackend() pada class User dengan role backend
     * Input: role = 'backend'
     * Expected Output: true
     */
    public function test_is_backend_returns_true_for_backend_role()
    {
        $user = new User();
        $user->role = 'backend';
        
        $this->assertTrue($user->isBackend());
    }
    
    /**
     * ID 9.2: Method isBackend() pada class User dengan role bukan backend
     * Input: role = 'admin'
     * Expected Output: false
     */
    public function test_is_backend_returns_false_for_non_backend_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertFalse($user->isBackend());
    }
    
    /**
     * ID 10.1: Method isDevops() pada class User dengan role devops
     * Input: role = 'devops'
     * Expected Output: true
     */
    public function test_is_devops_returns_true_for_devops_role()
    {
        $user = new User();
        $user->role = 'devops';
        
        $this->assertTrue($user->isDevops());
    }
    
    /**
     * ID 10.2: Method isDevops() pada class User dengan role bukan devops
     * Input: role = 'admin'
     * Expected Output: false
     */
    public function test_is_devops_returns_false_for_non_devops_role()
    {
        $user = new User();
        $user->role = 'admin';
        
        $this->assertFalse($user->isDevops());
    }
}

