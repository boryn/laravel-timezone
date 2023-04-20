<?php

namespace JamesMills\LaravelTimezone\Listeners\Auth;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Events\AccessTokenCreated;
use Torann\GeoIP\Location;

class UpdateUsersTimezone
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle($event)
    {
        $user = null;

        /**
         * If the event is AccessTokenCreated,
         * we logged the user and return,
         * stopping the execution.
         *
         * The Auth::loginUsingId dispatches a Login event,
         * making this listener be called again.
         */
        if ($event instanceof AccessTokenCreated) {
            Auth::loginUsingId($event->userId);

            return;
        }

        /**
         * If the event is Login, we get the user from the web guard.
         */
        if ($event instanceof Login) {
            $user = Auth::user();
        }

        /**
         * If no user is found, we just return. Nothing to do here.
         */
        if (is_null($user)) {
            return;
        }

        /**
         * Overwrite mode is not active and user timezone is already set. Nothing to do here.
         */
        if (config('timezone.overwrite') == false && $user->timezone != null) {
            return;
        }
        
        $ip = $this->getFromLookup();
        $geoip_info = geoip()->getLocation($ip);

        if ($user->timezone != $geoip_info['timezone']) {
            if (config('timezone.overwrite') == true || $user->timezone == null) {
                $user->timezone = $geoip_info['timezone'] ?? $geoip_info->time_zone['name'];

                $this->notify($geoip_info);
            }
        }
        
        // ps. country 'iso_code' using default is returned as two-letter country code ISO 3166-1 alpha-2
        // https://ip-api.com/docs/api:json
        // (which is compatible with https://github.com/petercoles/Multilingual-Country-List)
        $countryIsoCodeField = config('timezone.country_iso_code_field');
        if ($countryIsoCodeField) {
            if ($user->{$countryIsoCodeField} == null || $user->{$countryIsoCodeField} != $geoip_info['iso_code']) {
                $user->{$countryIsoCodeField} = $geoip_info['iso_code'] ?? null;
            }
        }

        $user->save();
    }

    /**
     * @param  Location  $geoip_info
     */
    private function notify(Location $geoip_info)
    {
        if (request()->hasSession() && config('timezone.flash') == 'off') {
            return;
        }

        $message = sprintf(config('timezone.message', 'We have set your timezone to %s'), $geoip_info['timezone']);

        if (config('timezone.flash') == 'laravel') {
            request()->session()->flash('success', $message);

            return;
        }

        if (config('timezone.flash') == 'laracasts') {
            flash()->success($message);

            return;
        }

        if (config('timezone.flash') == 'mercuryseries') {
            flashy()->success($message);

            return;
        }

        if (config('timezone.flash') == 'spatie') {
            flash()->success($message);

            return;
        }

        if (config('timezone.flash') == 'mckenziearts') {
            notify()->success($message);

            return;
        }

        if (config('timezone.flash') == 'tall-toasts') {
            toast()->success($message)->pushOnNextPage();

            return;
        }
    }

    /**
     * @return mixed
     */
    private function getFromLookup()
    {
        $result = null;

        foreach (config('timezone.lookup') as $type => $keys) {
            if (empty($keys)) {
                continue;
            }

            $result = $this->lookup($type, $keys);

            if (is_null($result)) {
                continue;
            }
        }

        return $result;
    }

    /**
     * @param $type
     * @param $keys
     * @return string|null
     */
    private function lookup($type, $keys)
    {
        $value = null;

        foreach ($keys as $key) {
            if (request()->$type->has($key)) {
                return request()->$type->get($key);
            }
        }

        return $value;
    }
}
