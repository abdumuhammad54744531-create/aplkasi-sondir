<?php
declare(strict_types=1);
function require_login(): void { if(empty($_SESSION['user'])) redirect('login.php'); }
function require_role(array|string $roles): void {
    require_login(); $roles=(array)$roles;
    if(!in_array($_SESSION['user']['level'],$roles,true)){ http_response_code(403); exit('Anda tidak memiliki hak akses.'); }
}
function can(array|string $roles): bool { return !empty($_SESSION['user']) && in_array($_SESSION['user']['level'],(array)$roles,true); }

