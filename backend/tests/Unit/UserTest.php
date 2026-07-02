<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * isAdminSistem() returns true for admin sistem role.
     */
    public function test_is_admin_sistem_returns_true_for_matching_role()
    {
        $user = new User();
        $user->role = 'admin_sistem';

        $this->assertTrue($user->isAdminSistem());
    }

    /**
     * isPengelolaAplikasi() returns true for pengelola aplikasi role.
     */
    public function test_is_pengelola_aplikasi_returns_true_for_matching_role()
    {
        $user = new User();
        $user->role = 'pengelola_aplikasi';

        $this->assertTrue($user->isPengelolaAplikasi());
    }

    /**
     * isAnalisDesain() returns true for analis desain role.
     */
    public function test_is_analis_desain_returns_true_for_matching_role()
    {
        $user = new User();
        $user->role = 'analis_desain';

        $this->assertTrue($user->isAnalisDesain());
    }

    /**
     * isTimImplementasiAplikasi() returns true for implementation team role.
     */
    public function test_is_tim_implementasi_aplikasi_returns_true_for_matching_role()
    {
        $user = new User();
        $user->role = 'tim_implementasi_aplikasi';

        $this->assertTrue($user->isTimImplementasiAplikasi());
    }

    /**
     * isTimImplementasiAplikasi() returns false for non-implementation role.
     */
    public function test_is_tim_implementasi_aplikasi_returns_false_for_non_implementation_role()
    {
        $user = new User();
        $user->role = 'pengelola_aplikasi';

        $this->assertFalse($user->isTimImplementasiAplikasi());
    }
    
    /**
     * isDevops() returns true for devops developer role.
     */
    public function test_is_devops_returns_true_for_devops_developer_role()
    {
        $user = new User();
        $user->role = 'devops_developer';
        
        $this->assertTrue($user->isDevops());
    }
    
    /**
     * isDevops() returns false for non-devops role.
     */
    public function test_is_devops_returns_false_for_non_devops_developer_role()
    {
        $user = new User();
        $user->role = 'pengelola_aplikasi';
        
        $this->assertFalse($user->isDevops());
    }
}
