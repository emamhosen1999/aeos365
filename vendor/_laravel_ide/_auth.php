<?php

namespace Illuminate\Contracts\Auth;

interface Guard
{
    /**
     * @return \Aero\Core\Models\User|null
     */
    public function user();
}