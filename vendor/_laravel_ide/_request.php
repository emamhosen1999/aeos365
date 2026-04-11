<?php

namespace Illuminate\Http;

interface Request
{
    /**
     * @return \Aero\Core\Models\User|null
     */
    public function user($guard = null);
}