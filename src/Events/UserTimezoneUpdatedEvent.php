<?php

declare(strict_types=1);

namespace JamesMills\LaravelTimezone\Events;

use Illuminate\Queue\SerializesModels;

class UserTimezoneUpdatedEvent
{
    use SerializesModels;

    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }
}
