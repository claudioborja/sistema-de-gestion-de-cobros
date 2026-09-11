<?php
namespace Config;
class Auth extends \CodeIgniter\Shield\Config\Auth
{
    public bool $allowRegistration = false;
    public bool $allowMagicLinkLogins = false;
    public array $views = ['login' => 'auth/login'];
    public array $redirects = ['register'=>'/', 'login'=>'/', 'logout'=>'login', 'force_reset'=>'/', 'permission_denied'=>'/', 'group_denied'=>'/'];
}
