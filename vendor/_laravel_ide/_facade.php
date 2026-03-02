<?php

namespace Illuminate\Support\Facades;

interface Auth
{
    /**
     * @return \Aero\Core\Models\User|false
     */
    public static function loginUsingId(mixed $id, bool $remember = false);

    /**
     * @return \Aero\Core\Models\User|false
     */
    public static function onceUsingId(mixed $id);

    /**
     * @return \Aero\Core\Models\User|null
     */
    public static function getUser();

    /**
     * @return \Aero\Core\Models\User
     */
    public static function authenticate();

    /**
     * @return \Aero\Core\Models\User|null
     */
    public static function user();

    /**
     * @return \Aero\Core\Models\User|null
     */
    public static function logoutOtherDevices(string $password);

    /**
     * @return \Aero\Core\Models\User
     */
    public static function getLastAttempted();
}